<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Hooks;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;

/**
 * Lets a copied glossary folder start without a glossary.
 *
 * Copying a page copies the records on it, the glossary record of a glossary folder and its
 * dictionaries included. The copy would carry the DeepL glossary id of the original folder, so
 * synchronising the copy would edit or remove the glossary of the original folder. The copied
 * records are removed instead, and the first synchronisation of the copy creates a glossary of
 * its own, named after the copy. The terms are copied as usual.
 *
 * The DataHandler gets its hooks through `GeneralUtility::makeInstance()`, which needs a public
 * service.
 */
#[Autoconfigure(public: true)]
final readonly class CopiedGlossaryFolderHook
{
    private const GLOSSARY_TABLE = 'tx_deepltranslate_glossary';
    private const DICTIONARY_TABLE = 'tx_deepltranslate_glossarydictionary';

    public function __construct(
        private ConnectionPool $connectionPool,
    ) {
    }

    /**
     * @param int|string $id
     * @param mixed $value
     */
    public function processCmdmap_postProcess(
        string $command,
        string $table,
        $id,
        $value,
        DataHandler $dataHandler,
    ): void {
        if ($command !== 'copy') {
            return;
        }
        $copiedGlossaries = $this->getCopiedUids($dataHandler, self::GLOSSARY_TABLE);
        $copiedDictionaries = $this->getCopiedUids($dataHandler, self::DICTIONARY_TABLE);
        if ($copiedGlossaries === [] && $copiedDictionaries === []) {
            return;
        }

        $this->connectionPool
            ->getConnectionForTable(self::GLOSSARY_TABLE)
            ->transactional(function () use ($copiedGlossaries, $copiedDictionaries): void {
                $this->deleteRecords(self::DICTIONARY_TABLE, 'uid', $copiedDictionaries);
                // A dictionary copied along with its glossary record belongs to the copy as well.
                $this->deleteRecords(self::DICTIONARY_TABLE, 'glossary', $copiedGlossaries);
                $this->deleteRecords(self::GLOSSARY_TABLE, 'uid', $copiedGlossaries);
            });
    }

    /**
     * @return list<int>
     */
    private function getCopiedUids(DataHandler $dataHandler, string $table): array
    {
        $copies = $dataHandler->copyMappingArray[$table] ?? [];

        return array_values(array_filter(
            array_map(intval(...), $copies),
            static fn (int $uid): bool => $uid > 0
        ));
    }

    /**
     * @param list<int> $values
     */
    private function deleteRecords(string $table, string $field, array $values): void
    {
        if ($values === []) {
            return;
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder
            ->delete($table)
            ->where(
                $queryBuilder->expr()->in(
                    $field,
                    $queryBuilder->createNamedParameter($values, Connection::PARAM_INT_ARRAY)
                )
            )
            ->executeStatement();
    }
}
