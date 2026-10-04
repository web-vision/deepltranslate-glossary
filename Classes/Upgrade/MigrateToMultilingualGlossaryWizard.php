<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Upgrade;

use DeepL\DeepLException;
use DeepL\GlossaryNotFoundException;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;
use WebVision\Deepltranslate\Glossary\Client\GlossaryAPIV3ClientInterface;
use WebVision\Deepltranslate\Glossary\Domain\Repository\GlossaryRepository;
use WebVision\Deepltranslate\Glossary\Service\GlossaryNameService;
use WebVision\Deepltranslate\Glossary\Service\MultilingualGlossaryService;

/**
 * Collapses the glossary records of the DeepL glossary API v2, which stored one glossary per
 * language pair, into the single glossary record per folder the API v3 works with.
 *
 * A folder still holding such records is not synchronised until this wizard ran. Remove that check
 * together with this wizard, see {@see MultilingualGlossaryService::syncGlossary()} and
 * {@see GlossaryRepository::hasGlossaryRecordOfApiV2()}.
 */
#[UpgradeWizard(identifier: 'deepltranslateGlossary_migrateToMultilingualGlossary')]
final readonly class MigrateToMultilingualGlossaryWizard implements UpgradeWizardInterface
{
    public function __construct(
        private ConnectionPool $connectionPool,
        private GlossaryAPIV3ClientInterface $client,
        private LoggerInterface $logger,
        private GlossaryNameService $glossaryNameService,
    ) {
    }

    public function getTitle(): string
    {
        return 'Migrate glossaries to the DeepL glossary API v3';
    }

    public function getDescription(): string
    {
        return 'Collapses the glossary records of a folder into a single record, removes the'
            . ' glossaries created with the DeepL glossary API v2 and detaches the folder, so'
            . ' that the next synchronization publishes it through the API v3.';
    }

    public function updateNecessary(): bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_deepltranslate_glossary');

        return (int)$queryBuilder
            ->count('uid')
            ->from('tx_deepltranslate_glossary')
            ->where(
                $queryBuilder->expr()->neq(
                    'source_lang',
                    $queryBuilder->createNamedParameter('', Connection::PARAM_STR)
                )
            )
            ->executeQuery()
            ->fetchOne() > 0;
    }

    public function executeUpdate(): bool
    {
        foreach ($this->getFolderIdsToMigrate() as $pageId) {
            $records = $this->getGlossaryRecordsOfFolder($pageId);
            if ($records === []) {
                continue;
            }
            $this->removeRemoteGlossaries($records);
            $this->collapseRecords($records, $pageId);
        }

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
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(static fn (array $row): int => (int)$row['pid'], $rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getGlossaryRecordsOfFolder(int $pageId): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_deepltranslate_glossary');

        return $queryBuilder
            ->select('uid', 'glossary_id', 'glossary_name')
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
    }

    /**
     * A glossary created through the API v2 covers a single language pair and cannot become a
     * dictionary of a multilingual glossary, so it is removed instead of being converted.
     *
     * @param array<int, array<string, mixed>> $records
     */
    private function removeRemoteGlossaries(array $records): void
    {
        foreach ($records as $record) {
            $glossaryId = (string)$record['glossary_id'];
            if ($glossaryId === '') {
                continue;
            }
            try {
                $this->client->deleteGlossary($glossaryId);
            } catch (GlossaryNotFoundException) {
                // Already gone at DeepL, so nothing is left to delete by hand.
            } catch (DeepLException $exception) {
                // Without a usable API key or connection the local migration still has to
                // happen, otherwise the installation stays on the old structure entirely.
                $this->logger->warning(sprintf(
                    'Glossary "%s" could not be removed from DeepL during migration: %s',
                    $glossaryId,
                    $exception->getMessage()
                ));
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $records
     */
    private function collapseRecords(array $records, int $pageId): void
    {
        $connection = $this->connectionPool->getConnectionForTable('tx_deepltranslate_glossary');
        // The dictionaries describe the removed glossaries, the next synchronisation stores new ones.
        foreach ($records as $record) {
            $this->connectionPool
                ->getConnectionForTable('tx_deepltranslate_glossarydictionary')
                ->delete('tx_deepltranslate_glossarydictionary', ['glossary' => (int)$record['uid']]);
        }
        $keptRecord = array_shift($records);
        if ($keptRecord === null) {
            return;
        }
        foreach ($records as $record) {
            $connection->delete('tx_deepltranslate_glossary', ['uid' => (int)$record['uid']]);
        }

        $connection->update(
            'tx_deepltranslate_glossary',
            [
                'glossary_id' => '',
                // The former name describes a single language pair, the glossary covers them all.
                'glossary_name' => $this->glossaryNameService->getGlossaryName($pageId),
                'glossary_lastsync' => 0,
                'glossary_ready' => 0,
                'source_lang' => '',
                'target_lang' => '',
            ],
            ['uid' => (int)$keptRecord['uid']]
        );
    }
}
