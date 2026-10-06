<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Upgrade;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\ChattyInterface;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;
use WebVision\Deepltranslate\Glossary\Domain\Repository\GlossaryRepository;
use WebVision\Deepltranslate\Glossary\Service\GlossaryNameService;
use WebVision\Deepltranslate\Glossary\Service\MultilingualGlossaryService;

/**
 * Collapses the glossary records of the DeepL glossary API v2, which stored one glossary per
 * language pair, into the single glossary record per folder the API v3 works with.
 *
 * The migration is local only. The glossaries of the API v2 stay at DeepL, because a copy of
 * the database sharing the API key, a staging system for example, would otherwise delete the
 * glossaries the live system still translates with. Their ids are kept in
 * {@see LegacyGlossaryIdStore} and reported, `deepl:glossary:cleanup --legacy` removes them.
 *
 * A folder still holding such records is not synchronised until this wizard ran. Remove that check
 * together with this wizard, see {@see MultilingualGlossaryService::syncGlossary()} and
 * {@see GlossaryRepository::hasGlossaryRecordOfApiV2()}.
 *
 * @todo The attribute and the interfaces of `TYPO3\CMS\Install` are deprecated since TYPO3 v14
 *       (#106947). Use `TYPO3\CMS\Core\Attribute\UpgradeWizard` and `TYPO3\CMS\Core\Upgrades`
 *       once TYPO3 v13 is no longer supported. Implementing the deprecated ChattyInterface
 *       serves both versions, it extends the one of `TYPO3\CMS\Core` on v14.
 */
#[UpgradeWizard(identifier: 'deepltranslateGlossary_migrateToMultilingualGlossary')]
final class MigrateToMultilingualGlossaryWizard implements UpgradeWizardInterface, ChattyInterface
{
    /**
     * Set by the core before the wizard runs, the only state of this service.
     */
    private ?OutputInterface $output = null;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly LoggerInterface $logger,
        private readonly GlossaryNameService $glossaryNameService,
        private readonly LegacyGlossaryIdStore $legacyGlossaryIdStore,
        private readonly MigrateTablesFromOldStructureWizard $oldStructureWizard,
        private readonly Registry $registry,
    ) {
    }

    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    public function getTitle(): string
    {
        return 'Migrate glossaries to the DeepL glossary API v3';
    }

    public function getDescription(): string
    {
        return 'Collapses the glossary records of a folder into a single record and detaches the'
            . ' folder, so that the next synchronization publishes it through the API v3. The'
            . ' glossaries created with the DeepL glossary API v2 are kept at DeepL and listed,'
            . ' remove them with "deepl:glossary:cleanup --legacy" once no other instance uses them.';
    }

    /**
     * Stays necessary while the tables of deepltranslate 4.x still have to be copied, as the core
     * marks a wizard done which is not necessary before any wizard of the same run is executed.
     */
    public function updateNecessary(): bool
    {
        return $this->isOldStructureMigrationPending() || $this->getFolderIdsToMigrate() !== [];
    }

    public function executeUpdate(): bool
    {
        if ($this->isOldStructureMigrationPending()) {
            $this->output?->writeln(sprintf(
                '<error>The glossaries of deepltranslate 4.x are not copied yet. Run the upgrade wizard "%s"'
                . ' (deepltranslateGlossary_migrateGlossaryTables) first, then this wizard again.</error>',
                $this->oldStructureWizard->getTitle()
            ));
            return false;
        }

        $leftBehindGlossaryIds = [];
        foreach ($this->getFolderIdsToMigrate() as $pageId) {
            $leftBehindGlossaryIds = [...$leftBehindGlossaryIds, ...$this->migrateFolder($pageId)];
        }
        $leftBehindGlossaryIds = $this->withoutReferencedGlossaryIds($leftBehindGlossaryIds);
        $this->legacyGlossaryIdStore->add($leftBehindGlossaryIds);
        $this->reportLeftBehindGlossaries($leftBehindGlossaryIds);

        return true;
    }

    /**
     * @return class-string[]
     */
    public function getPrerequisites(): array
    {
        return [
            DatabaseUpdatedPrerequisite::class,
        ];
    }

    /**
     * The tables of deepltranslate 4.x are copied only into empty tables, so they are waited for
     * only as long as that copy can still happen.
     */
    private function isOldStructureMigrationPending(): bool
    {
        if ($this->registry->get('installUpdate', MigrateTablesFromOldStructureWizard::class, false)) {
            return false;
        }

        return $this->countAllRows('tx_deepltranslate_glossary') === 0
            && $this->countAllRows('tx_deepltranslate_glossaryentry') === 0
            && $this->oldStructureWizard->updateNecessary();
    }

    private function countAllRows(string $table): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();

        return (int)$queryBuilder
            ->count('*')
            ->from($table)
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * @return int[]
     */
    private function getFolderIdsToMigrate(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_deepltranslate_glossary');
        $rows = $queryBuilder
            ->select('pid')
            ->distinct()
            ->from('tx_deepltranslate_glossary')
            ->where(
                $queryBuilder->expr()->neq(
                    'source_lang',
                    $queryBuilder->createNamedParameter('', Connection::PARAM_STR)
                )
            )
            ->orderBy('pid')
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(static fn (array $row): int => (int)$row['pid'], $rows);
    }

    /**
     * Keeps a record of the API v3 the folder holds already, with its glossary and dictionaries.
     * Otherwise the record of the API v2 with the lowest uid is kept and detached. The other
     * records of the API v2 are removed.
     *
     * @return string[] The ids of the glossaries of the API v2 the folder pointed at
     */
    private function migrateFolder(int $pageId): array
    {
        $records = $this->getGlossaryRecordsOfFolder($pageId);
        $recordsOfApiV2 = array_values(array_filter(
            $records,
            static fn (array $record): bool => $record['source_lang'] !== ''
        ));
        if ($recordsOfApiV2 === []) {
            return [];
        }
        $keptRecord = null;
        foreach ($records as $record) {
            if ($record['source_lang'] === '') {
                $keptRecord = $record;
                break;
            }
        }
        $recordToDetach = null;
        if ($keptRecord === null) {
            $recordToDetach = array_shift($recordsOfApiV2);
        }
        // Chosen before the transaction, a listener of the name event may query the database.
        $glossaryName = $recordToDetach !== null ? $this->glossaryNameService->getGlossaryName($pageId) : '';

        $this->collapseRecords($recordsOfApiV2, $recordToDetach, $glossaryName);

        $glossaryIds = array_map(static fn (array $record): string => $record['glossary_id'], $recordsOfApiV2);
        if ($recordToDetach !== null) {
            $glossaryIds[] = $recordToDetach['glossary_id'];
        }

        return array_values(array_filter($glossaryIds, static fn (string $glossaryId): bool => $glossaryId !== ''));
    }

    /**
     * @return list<array{uid: int, glossary_id: string, source_lang: string}>
     */
    private function getGlossaryRecordsOfFolder(int $pageId): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_deepltranslate_glossary');
        $rows = $queryBuilder
            ->select('uid', 'glossary_id', 'source_lang')
            ->from('tx_deepltranslate_glossary')
            ->where(
                $queryBuilder->expr()->eq(
                    'pid',
                    $queryBuilder->createNamedParameter($pageId, Connection::PARAM_INT)
                )
            )
            ->orderBy('uid')
            ->executeQuery()
            ->fetchAllAssociative();

        // Released versions left glossary_id nullable.
        return array_map(static fn (array $row): array => [
            'uid' => (int)$row['uid'],
            'glossary_id' => (string)$row['glossary_id'],
            'source_lang' => (string)$row['source_lang'],
        ], $rows);
    }

    /**
     * @param list<array{uid: int, glossary_id: string, source_lang: string}> $recordsToRemove
     * @param array{uid: int, glossary_id: string, source_lang: string}|null $recordToDetach
     */
    private function collapseRecords(array $recordsToRemove, ?array $recordToDetach, string $glossaryName): void
    {
        // A folder is either migrated completely or not at all, so a failure leaves it to a re-run.
        $this->connectionPool
            ->getConnectionForTable('tx_deepltranslate_glossary')
            ->transactional(function (Connection $connection) use ($recordsToRemove, $recordToDetach, $glossaryName): void {
                $dictionaryConnection = $this->connectionPool->getConnectionForTable('tx_deepltranslate_glossarydictionary');
                foreach ($recordsToRemove as $record) {
                    $dictionaryConnection->delete('tx_deepltranslate_glossarydictionary', ['glossary' => $record['uid']]);
                    $connection->delete('tx_deepltranslate_glossary', ['uid' => $record['uid']]);
                }
                if ($recordToDetach === null) {
                    return;
                }
                // The dictionaries describe the glossary left behind, the next synchronisation stores new ones.
                $dictionaryConnection->delete('tx_deepltranslate_glossarydictionary', ['glossary' => $recordToDetach['uid']]);
                $connection->update(
                    'tx_deepltranslate_glossary',
                    [
                        'glossary_id' => '',
                        // The former name describes a single language pair, the glossary covers them all.
                        'glossary_name' => $glossaryName,
                        'glossary_lastsync' => 0,
                        'glossary_ready' => 0,
                        'source_lang' => '',
                        'target_lang' => '',
                    ],
                    ['uid' => $recordToDetach['uid']]
                );
            });
    }

    /**
     * A glossary another record still points at is in use and never listed for removal.
     *
     * @param string[] $glossaryIds
     * @return list<string>
     */
    private function withoutReferencedGlossaryIds(array $glossaryIds): array
    {
        $glossaryIds = array_values(array_unique($glossaryIds));
        if ($glossaryIds === []) {
            return [];
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_deepltranslate_glossary');
        $queryBuilder->getRestrictions()->removeAll()->add(new DeletedRestriction());
        $referencedGlossaryIds = $queryBuilder
            ->select('glossary_id')
            ->from('tx_deepltranslate_glossary')
            ->where(
                $queryBuilder->expr()->in(
                    'glossary_id',
                    $queryBuilder->createNamedParameter($glossaryIds, Connection::PARAM_STR_ARRAY)
                )
            )
            ->executeQuery()
            ->fetchFirstColumn();

        return array_values(array_diff($glossaryIds, array_map('strval', $referencedGlossaryIds)));
    }

    /**
     * @param list<string> $glossaryIds
     */
    private function reportLeftBehindGlossaries(array $glossaryIds): void
    {
        if ($glossaryIds === []) {
            return;
        }
        $this->logger->notice(
            'The glossary migration kept {count} glossaries of the DeepL glossary API v2 at DeepL: {glossaryIds}.'
            . ' Remove them with "deepl:glossary:cleanup --legacy".',
            ['count' => count($glossaryIds), 'glossaryIds' => implode(', ', $glossaryIds)]
        );
        if ($this->output === null) {
            return;
        }
        $this->output->writeln([
            sprintf('The glossaries of the DeepL glossary API v2 were kept at DeepL (%d):', count($glossaryIds)),
            '',
        ]);
        foreach ($glossaryIds as $glossaryId) {
            $this->output->writeln(sprintf('  vendor/bin/typo3 deepl:glossary:cleanup --glossaryId %s', $glossaryId));
        }
        $this->output->writeln([
            '',
            'Once no other instance sharing the API key uses them, remove all of them with:',
            '',
            '  vendor/bin/typo3 deepl:glossary:cleanup --legacy',
        ]);
    }
}
