<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Schema;

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
