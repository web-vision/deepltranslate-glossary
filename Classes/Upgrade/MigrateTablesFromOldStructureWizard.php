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

        if (!$this->tablesEmpty()) {
            return false;
        }

        $this->updateTable($tableGlossary, 'tx_deepltranslate_glossary');
        $this->updateTable($tableEntry, 'tx_deepltranslate_glossaryentry');

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

        $deletedTableGlossary = sprintf('zzz_deleted_%s', $tableGlossary);
        $deletedTableEntry = sprintf('zzz_deleted_%s', $tableEntry);
        if (
            in_array($deletedTableGlossary, $existingTables)
            && in_array($deletedTableEntry, $existingTables)
        ) {
            $this->setTablesDeleted();
            $foundTables = true;
            $tableGlossary = $deletedTableGlossary;
            $tableEntry = $deletedTableEntry;
        }
        if (!$foundTables) {
            return false;
        }

        $countGlossary = $this->countTable($tableGlossary, true);
        $countEntry = $this->countTable($tableEntry, true);

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

    private function updateTable(string $oldTable, string $newTable): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($newTable);
        $selectStatement = $queryBuilder
            ->select('*')
            ->from($oldTable)
            ->where(
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            );
        $result = $selectStatement->executeQuery();
        while ($entry = $result->fetchAssociative()) {
            // remove potentially existing breaking fields
            // https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/12.0/Breaking-98024-TCA-option-cruserid-removed.html
            unset($entry['cruser_id']);
            // remove potentially set deleted fields
            foreach ($entry as $fieldName => $_) {
                if (str_starts_with('zzz_deleted_', $fieldName)) {
                    unset($entry[$fieldName]);
                }
            }
            $insertQueryBuilder = $this->connectionPool->getQueryBuilderForTable($newTable);
            $insertQueryBuilder
                ->insert($newTable)
                ->values($entry)
                ->executeStatement();
        }
    }

    private function tablesEmpty(): bool
    {
        return
            $this->countTable('tx_deepltranslate_glossary') === 0
            && $this->countTable('tx_deepltranslate_glossaryentry') === 0
        ;
    }

    private function countTable(string $tableName, bool $respectDeleted = false): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_deepltranslate_glossary');
        $countQuery = $queryBuilder
            ->count('*')
            ->from($tableName);
        if ($respectDeleted) {
            $countQuery->where(
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            );
        }
        return (int)$countQuery->executeQuery()->fetchOne();
    }
}
