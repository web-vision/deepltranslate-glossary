<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Upgrade;

use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\ReferenceIndexUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

#[UpgradeWizard(identifier: 'deepltranslateGlossary_migrateGlossaryTables')]
final class MigrateTablesFromOldStructureWizard implements UpgradeWizardInterface
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly FrontendInterface $cache
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getTitle(): string
    {
        return 'Glossary table migration';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Migrates the old wvdeepltranslate tables to the new ones';
    }

    /**
     * @inheritDoc
     */
    public function executeUpdate(): bool
    {
        $tableGlossary = 'tx_wvdeepltranslate_glossary';
        $tableEntry = 'tx_wvdeepltranslate_glossaryentry';
        if ($this->isTableDeleted()) {
            $tableGlossary = sprintf('zzz_deleted_%s', $tableGlossary);
            $tableEntry = sprintf('zzz_deleted_%s', $tableEntry);
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_deepltranslate_glossary');
        $selectGlossariesStatement = $queryBuilder
            ->select(
                'pid',
                'glossary_ready',
                'glossary_lastsync',
                'glossary_id',
                'glossary_name',
                'source_lang',
                'target_lang',
                'sys_language_uid',
            )
            ->from($tableGlossary)
            ->where(
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            );
        $glossariesResult = $selectGlossariesStatement->executeQuery();

        $data = [
            '',
        ];
        while ($glossary = $glossariesResult->fetchAssociative()) {

        }
        return true;
    }

    /**
     * @inheritDoc
     */
    public function updateNecessary(): bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_deepltranslate_glossary');
        $existingTables = $queryBuilder->getConnection()->getSchemaInformation()->listTableNames();
        $foundTables = true;
        $tableGlossary = 'tx_wvdeepltranslate_glossary';
        $tableEntry = 'tx_wvdeepltranslate_glossaryentry';
        // old tables are not existing, quit
        if (
            !in_array($tableGlossary, $existingTables)
            && !in_array($tableEntry, $existingTables)
        ) {
            $foundTables = false;
        }

        $tableGlossary = sprintf('zzz_deleted_%s', $tableGlossary);
        $tableEntry = sprintf('zzz_deleted_%s', $tableEntry);
        if (
            !in_array($tableGlossary, $existingTables)
            && !in_array($tableEntry, $existingTables)
        ) {
            $this->setTablesDeleted();
            $foundTables = true;
        }
        if (!$foundTables) {
            return false;
        }

        $countGlossaryStatement = $queryBuilder
            ->count('*')
            ->from($tableGlossary)
            ->where(
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            );
        $countGlossary = (int)$countGlossaryStatement->executeQuery()->fetchOne();

        $countEntryStatement = $queryBuilder
            ->count('*')
            ->from($tableEntry)
            ->where(
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            );
        $countEntry = (int)$countEntryStatement->executeQuery()->fetchOne();

        if ($countGlossary === 0 && $countEntry === 0) {
            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function getPrerequisites(): array
    {
        return [
            DatabaseUpdatedPrerequisite::class,
            ReferenceIndexUpdatedPrerequisite::class,
        ];
    }

    private function isTableDeleted(): bool
    {
        if ($this->cache->has('deepltranslateGlossary_migrateGlossaryTables')) {
            return $this->cache->get('deepltranslateGlossary_migrateGlossaryTables');
        }
        return false;
    }

    private function setTablesDeleted(): void
    {
        $this->cache->set('deepltranslateGlossary_migrateGlossaryTables', true);
    }
}
