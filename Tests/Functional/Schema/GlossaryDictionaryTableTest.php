<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Schema;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use WebVision\Deepltranslate\Glossary\Tests\Functional\AbstractDeepLTestCase;

/**
 * A glossary folder maps to exactly one DeepL glossary holding one dictionary per language pair.
 * The language pair therefore lives on the dictionary records, no longer on the glossary itself.
 */
final class GlossaryDictionaryTableTest extends AbstractDeepLTestCase
{
    #[Test]
    public function dictionariesAreStoredForOneGlossary(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/glossaryWithDictionaries.csv');

        $queryBuilder = $this->get(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_deepltranslate_glossarydictionary');
        $dictionaries = $queryBuilder
            ->select('source_lang', 'target_lang', 'entry_count', 'in_sync')
            ->from('tx_deepltranslate_glossarydictionary')
            ->where(
                $queryBuilder->expr()->eq(
                    'glossary',
                    $queryBuilder->createNamedParameter(1, Connection::PARAM_INT)
                )
            )
            ->orderBy('uid')
            ->executeQuery()
            ->fetchAllAssociative();

        self::assertCount(2, $dictionaries);
        self::assertSame('de', $dictionaries[0]['source_lang']);
        self::assertSame('en', $dictionaries[0]['target_lang']);
        self::assertSame(2, (int)$dictionaries[0]['entry_count']);
        self::assertSame(1, (int)$dictionaries[0]['in_sync']);
        self::assertSame('en', $dictionaries[1]['source_lang']);
        self::assertSame('fr', $dictionaries[1]['target_lang']);
        self::assertSame(0, (int)$dictionaries[1]['in_sync']);
    }

    /**
     * Every translation resolves its glossary by language pair through the dictionaries, joined on
     * the glossary they belong to.
     *
     * @return \Generator<string, array{columns: list<string>}>
     */
    public static function indexedLookupColumns(): \Generator
    {
        yield 'glossary the dictionary belongs to' => [
            'columns' => ['glossary'],
        ];
        yield 'language pair of the dictionary' => [
            'columns' => ['source_lang', 'target_lang'],
        ];
    }

    /**
     * @param list<string> $columns
     */
    #[Test]
    #[DataProvider('indexedLookupColumns')]
    public function dictionaryLookupColumnsAreIndexed(array $columns): void
    {
        $indexes = $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_deepltranslate_glossarydictionary')
            ->createSchemaManager()
            ->listTableIndexes('tx_deepltranslate_glossarydictionary');

        $indexedColumns = [];
        foreach ($indexes as $index) {
            $indexedColumns[] = $index->getColumns();
        }
        self::assertContains($columns, $indexedColumns);
    }

    /**
     * These columns were nullable in released versions. Deriving them from the TCA would make
     * them NOT NULL, which fails the database compare of an instance holding a NULL value.
     *
     * @return \Generator<string, array{table: string, column: string}>
     */
    public static function formerlyNullableColumns(): \Generator
    {
        yield 'term of a glossary entry' => [
            'table' => 'tx_deepltranslate_glossaryentry',
            'column' => 'term',
        ];
        yield 'DeepL id of a glossary' => [
            'table' => 'tx_deepltranslate_glossary',
            'column' => 'glossary_id',
        ];
        yield 'ready state of a glossary' => [
            'table' => 'tx_deepltranslate_glossary',
            'column' => 'glossary_ready',
        ];
    }

    #[Test]
    #[DataProvider('formerlyNullableColumns')]
    public function formerlyNullableColumnStaysNullable(string $table, string $column): void
    {
        $columns = $this->get(ConnectionPool::class)
            ->getConnectionForTable($table)
            ->createSchemaManager()
            ->listTableColumns($table);

        self::assertFalse($columns[$column]->getNotnull());
    }

    #[Test]
    public function glossaryRecordCarriesNoLanguagePair(): void
    {
        $glossarySchema = $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_deepltranslate_glossary')
            ->createSchemaManager()
            ->listTableColumns('tx_deepltranslate_glossary');
        $columns = array_keys($glossarySchema);

        // The glossary record is the single entry point per folder, so it keeps the DeepL id and
        // the synchronisation state only.
        self::assertContains('glossary_id', $columns);
        self::assertContains('glossary_ready', $columns);
        self::assertContains('dictionaries', $columns);
    }
}
