<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Service;

use DeepL\GlossaryInfo;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use WebVision\Deepltranslate\Glossary\Domain\Repository\GlossaryRepository;
use WebVision\Deepltranslate\Glossary\Service\DeeplGlossaryService;
use WebVision\Deepltranslate\Glossary\Tests\Functional\AbstractDeepLTestCase;

/**
 * The deprecated glossary handling of the API v2 must not bring back the glossary records per
 * language pair, which the upgrade wizard collapsed into one record per folder.
 */
final class DeeplGlossaryServiceTest extends AbstractDeepLTestCase
{
    use SiteBasedTestTrait;

    protected const LANGUAGE_PRESETS = [
        'EN' => [
            'id' => 0,
            'title' => 'English',
            'locale' => 'en_US.UTF-8',
            'iso' => 'en',
            'hrefLang' => 'en-US',
            'direction' => '',
            'custom' => [
                'deeplTargetLanguage' => '',
            ],
        ],
        'DE' => [
            'id' => 1,
            'title' => 'Deutsch',
            'locale' => 'de_DE',
            'iso' => 'de',
            'hrefLang' => 'de-DE',
            'direction' => '',
            'custom' => [
                'deeplTargetLanguage' => 'DE',
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->writeSiteConfiguration(
            identifier: 'acme',
            site: $this->buildSiteConfiguration(rootPageId: 1),
            languages: [
                $this->buildDefaultLanguageConfiguration('EN', '/'),
                $this->buildLanguageConfiguration('DE', '/de/', ['EN'], 'strict'),
            ],
        );

        $this->importCSVDataSet(__DIR__ . '/Fixtures/glossaryFolder.csv');
        $this->setUpBackendUser(1);
    }

    #[Test]
    public function syncingThroughTheDeprecatedServicePublishesOneGlossaryPerFolder(): void
    {
        $subject = $this->get(DeeplGlossaryService::class);

        $subject->syncGlossaries(2);

        $glossaries = $this->fetchGlossaryRecords();
        self::assertCount(1, $glossaries);
        self::assertNotSame('', $glossaries[0]['glossary_id']);
        self::assertSame('', $glossaries[0]['source_lang']);
        self::assertSame('', $glossaries[0]['target_lang']);
        // Only the API v3 synchronisation stores dictionaries.
        self::assertSame(1, $this->countDictionaryRecords());
    }

    #[Test]
    #[IgnoreDeprecations]
    public function readingGlossariesPerLanguagePairIsDeprecated(): void
    {
        $this->expectUserDeprecationMessageMatches('/getGlossaryInformationForSync\(\) is deprecated/');
        // It creates the record of every language pair through the other deprecated method.
        $this->expectUserDeprecationMessageMatches('/getGlossaryBySourceAndTargetForSync\(\) is deprecated/');
        $subject = $this->get(GlossaryRepository::class);

        $subject->getGlossaryInformationForSync(2);
    }

    #[Test]
    #[IgnoreDeprecations]
    public function readingGlossariesPerLanguagePairSkipsTranslationInRemovedLanguage(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/translationInRemovedLanguage.csv');
        $subject = $this->get(GlossaryRepository::class);

        $glossaries = $subject->getGlossaryInformationForSync(2);

        self::assertCount(1, $glossaries);
        self::assertSame('de', $glossaries[0]->targetLanguage);
        self::assertSame(
            [
                [
                    'source' => 'glossary term',
                    'target' => 'Glossareintrag',
                ],
                [
                    'source' => 'proton beam',
                    'target' => 'Protonenstrahl',
                ],
            ],
            $glossaries[0]->entries
        );
    }

    #[Test]
    #[IgnoreDeprecations]
    public function creatingAGlossaryPerLanguagePairIsDeprecated(): void
    {
        $this->expectUserDeprecationMessageMatches('/getGlossaryBySourceAndTargetForSync\(\) is deprecated/');
        $subject = $this->get(GlossaryRepository::class);

        $subject->getGlossaryBySourceAndTargetForSync('en', 'de', ['uid' => 2, 'title' => 'Glossary']);
    }

    #[Test]
    #[IgnoreDeprecations]
    public function storingTheStateOfAGlossaryPerLanguagePairIsDeprecated(): void
    {
        $this->expectUserDeprecationMessageMatches('/updateLocalGlossary\(\) is deprecated/');
        $subject = $this->get(GlossaryRepository::class);

        $subject->updateLocalGlossary(new GlossaryInfo('glossary-of-api-v2', 'Glossary', true, 'en', 'de', new \DateTime(), 2), 1);
    }

    #[Test]
    public function cachedEmptyLanguagePairsAreFetchedAgain(): void
    {
        $cache = $this->get(CacheManager::class)->getCache('deepltranslate_glossary');
        $cache->set('wv-deepl-glossary-pairs', []);
        $subject = $this->get(DeeplGlossaryService::class);

        $languagePairs = $subject->getPossibleGlossaryLanguageConfig();

        self::assertNotSame([], $languagePairs);
        self::assertSame($languagePairs, $cache->get('wv-deepl-glossary-pairs'));
    }

    private function countDictionaryRecords(): int
    {
        $queryBuilder = $this->get(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_deepltranslate_glossarydictionary');

        return (int)$queryBuilder
            ->count('uid')
            ->from('tx_deepltranslate_glossarydictionary')
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchGlossaryRecords(): array
    {
        $queryBuilder = $this->get(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_deepltranslate_glossary');

        return $queryBuilder
            ->select('uid', 'glossary_id', 'source_lang', 'target_lang')
            ->from('tx_deepltranslate_glossary')
            ->where(
                $queryBuilder->expr()->eq(
                    'pid',
                    $queryBuilder->createNamedParameter(2, Connection::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }
}
