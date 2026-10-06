<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Service;

use DeepL\MultilingualGlossaryDictionaryInfo;
use Doctrine\DBAL\Exception as DBALException;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use Symfony\Component\DependencyInjection\Container;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\EventDispatcher\ListenerProvider;
use WebVision\Deepltranslate\Glossary\Domain\Dto\GlossaryLanguageCollisionReason;
use WebVision\Deepltranslate\Glossary\Event\AfterGlossarySynchronizedEvent;
use WebVision\Deepltranslate\Glossary\Exception\GlossaryFolderNotSyncableException;
use WebVision\Deepltranslate\Glossary\Service\MultilingualGlossaryService;
use WebVision\Deepltranslate\Glossary\Tests\Functional\AbstractDeepLTestCase;
use WebVision\Deepltranslate\Glossary\Tests\Functional\Service\Fixtures\CollectingGlossarySynchronizedListener;

/**
 * Extensions react to a glossary folder synchronised with DeepL, for example to flag its terms,
 * so the event has to tell the stored outcome and nothing that did not happen.
 */
final class AfterGlossarySynchronizedEventTest extends AbstractDeepLTestCase
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
        'DE-DEFAULT' => [
            'id' => 0,
            'title' => 'Deutsch',
            'locale' => 'de_DE.UTF-8',
            'iso' => 'de',
            'hrefLang' => 'de-DE',
            'direction' => '',
            'custom' => [
                'deeplTargetLanguage' => '',
            ],
        ],
        'EN-GB' => [
            'id' => 1,
            'title' => 'English (UK)',
            'locale' => 'en_GB.UTF-8',
            'iso' => 'en',
            'hrefLang' => 'en-GB',
            'direction' => '',
            'custom' => [
                'deeplTargetLanguage' => 'EN-GB',
            ],
        ],
        'EN-US' => [
            'id' => 2,
            'title' => 'English (US)',
            'locale' => 'en_US.UTF-8',
            'iso' => 'en',
            'hrefLang' => 'en-US',
            'direction' => '',
            'custom' => [
                'deeplTargetLanguage' => 'EN-US',
            ],
        ],
    ];

    #[Test]
    public function syncPassesThePublishedGlossaryToTheListener(): void
    {
        $this->setUpEnglishGermanGlossaryFolder();
        $listener = $this->registerCollectingListener();

        $this->get(MultilingualGlossaryService::class)->syncGlossary(2);

        self::assertCount(1, $listener->events);
        $event = $listener->events[0];
        self::assertSame(2, $event->pageId);
        self::assertTrue($event->hasGlossary);
        self::assertSame($this->fetchStoredGlossaryId(), $event->glossaryId);
        self::assertNotSame('', $event->glossaryId);
        self::assertSame(
            [['sourceLang' => 'en', 'targetLang' => 'de', 'entryCount' => 2]],
            $this->describeDictionaries($event->dictionaries)
        );
        self::assertSame([], $event->collisions);
    }

    #[Test]
    public function repeatedSyncPassesTheKeptGlossaryToTheListener(): void
    {
        $this->setUpEnglishGermanGlossaryFolder();
        $subject = $this->get(MultilingualGlossaryService::class);
        $subject->syncGlossary(2);
        $glossaryId = $this->fetchStoredGlossaryId();
        $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_deepltranslate_glossaryentry')
            ->delete('tx_deepltranslate_glossaryentry', ['uid' => 4]);
        $listener = $this->registerCollectingListener();

        $subject->syncGlossary(2);

        self::assertCount(1, $listener->events);
        self::assertSame($glossaryId, $listener->events[0]->glossaryId);
        self::assertSame(
            [['sourceLang' => 'en', 'targetLang' => 'de', 'entryCount' => 1]],
            $this->describeDictionaries($listener->events[0]->dictionaries)
        );
    }

    #[Test]
    public function syncOfEmptiedFolderPassesTheRemovedGlossaryToTheListener(): void
    {
        $this->setUpEnglishGermanGlossaryFolder();
        $subject = $this->get(MultilingualGlossaryService::class);
        $subject->syncGlossary(2);
        $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_deepltranslate_glossaryentry')
            ->delete('tx_deepltranslate_glossaryentry', ['pid' => 2]);
        $listener = $this->registerCollectingListener();

        $subject->syncGlossary(2);

        self::assertCount(1, $listener->events);
        $event = $listener->events[0];
        self::assertSame(2, $event->pageId);
        self::assertFalse($event->hasGlossary);
        self::assertSame('', $event->glossaryId);
        self::assertSame([], $event->dictionaries);
    }

    #[Test]
    public function rejectedSyncDispatchesNoEvent(): void
    {
        $this->setUpEnglishGermanGlossaryFolder();
        $this->get(ConnectionPool::class)
            ->getConnectionForTable('pages')
            ->update('pages', ['hidden' => 1], ['uid' => 2]);
        $listener = $this->registerCollectingListener();

        try {
            $this->get(MultilingualGlossaryService::class)->syncGlossary(2);
            self::fail('Synchronising a hidden glossary folder has to be rejected.');
        } catch (GlossaryFolderNotSyncableException $exception) {
            self::assertSame(1791123486, $exception->getCode());
        }

        self::assertSame([], $listener->events);
    }

    #[Test]
    public function syncOfTermsFormingNoPairDispatchesNoEvent(): void
    {
        $this->setUpEnglishGermanGlossaryFolder();
        $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_deepltranslate_glossaryentry')
            ->update('tx_deepltranslate_glossaryentry', ['term' => '   '], ['sys_language_uid' => 1]);
        $listener = $this->registerCollectingListener();

        try {
            $this->get(MultilingualGlossaryService::class)->syncGlossary(2);
            self::fail('A folder whose terms yield no usable pair has to be reported.');
        } catch (GlossaryFolderNotSyncableException $exception) {
            self::assertSame(1791129095, $exception->getCode());
        }

        self::assertSame([], $listener->events);
    }

    #[Test]
    public function syncFailingToStoreTheGlossaryDispatchesNoEvent(): void
    {
        $this->setUpEnglishGermanGlossaryFolder();
        $connection = $this->get(ConnectionPool::class)->getConnectionForTable('tx_deepltranslate_glossarydictionary');
        // Makes storing the dictionaries fail after DeepL created the glossary.
        $connection->executeStatement('ALTER TABLE tx_deepltranslate_glossarydictionary RENAME TO tx_deepltranslate_glossarydictionary_away');
        $listener = $this->registerCollectingListener();

        try {
            $this->get(MultilingualGlossaryService::class)->syncGlossary(2);
            self::fail('A failure to store the synchronised glossary has to be reported.');
        } catch (DBALException) {
        } finally {
            $connection->executeStatement('ALTER TABLE tx_deepltranslate_glossarydictionary_away RENAME TO tx_deepltranslate_glossarydictionary');
        }

        self::assertSame([], $listener->events);
    }

    #[Test]
    public function syncPassesUnresolvedLanguageCodeCollisionsToTheListener(): void
    {
        $this->writeSiteConfiguration(
            identifier: 'acme',
            site: $this->buildSiteConfiguration(rootPageId: 1),
            languages: [
                $this->buildDefaultLanguageConfiguration('DE-DEFAULT', '/'),
                $this->buildLanguageConfiguration('EN-GB', '/en-gb/'),
                $this->buildLanguageConfiguration('EN-US', '/en-us/'),
            ],
        );
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/SharedLanguageCode/sharedLanguageCodeEnglishVariants.csv');
        $this->setUpBackendUser(1);
        $listener = $this->registerCollectingListener();

        $this->get(MultilingualGlossaryService::class)->syncGlossary(2);

        self::assertCount(1, $listener->events);
        $collisions = $listener->events[0]->collisions;
        self::assertCount(1, $collisions);
        self::assertSame('en', $collisions[0]->languageCode);
        self::assertSame(GlossaryLanguageCollisionReason::NoPreferredLanguage, $collisions[0]->reason);
        self::assertSame(1, $collisions[0]->selectedLanguage->getLanguageId());
        self::assertTrue($listener->events[0]->hasGlossary);
    }

    private function setUpEnglishGermanGlossaryFolder(): void
    {
        $this->writeSiteConfiguration(
            identifier: 'acme',
            site: $this->buildSiteConfiguration(rootPageId: 1),
            languages: [
                $this->buildDefaultLanguageConfiguration('EN', '/'),
                $this->buildLanguageConfiguration('DE', '/de/', ['EN'], 'strict'),
            ],
        );
        $this->importCSVDataSet(__DIR__ . '/Fixtures/glossaryFolder.csv');
        // Resolving the localizations of a glossary folder goes through
        // TranslationConfigurationProvider, which requires an authenticated backend user.
        $this->setUpBackendUser(1);
    }

    private function registerCollectingListener(): CollectingGlossarySynchronizedListener
    {
        $listener = new CollectingGlossarySynchronizedListener();
        /** @var Container $container */
        $container = $this->getContainer();
        $container->set('glossary-synchronized-listener', $listener);
        $this->get(ListenerProvider::class)->addListener(AfterGlossarySynchronizedEvent::class, 'glossary-synchronized-listener');

        return $listener;
    }

    private function fetchStoredGlossaryId(): string
    {
        return (string)$this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_deepltranslate_glossary')
            ->select(['glossary_id'], 'tx_deepltranslate_glossary', ['pid' => 2])
            ->fetchOne();
    }

    /**
     * @param list<MultilingualGlossaryDictionaryInfo> $dictionaries
     * @return list<array{sourceLang: string, targetLang: string, entryCount: int}>
     */
    private function describeDictionaries(array $dictionaries): array
    {
        return array_map(
            static fn (MultilingualGlossaryDictionaryInfo $dictionary): array => [
                'sourceLang' => $dictionary->sourceLang,
                'targetLang' => $dictionary->targetLang,
                'entryCount' => $dictionary->entryCount,
            ],
            $dictionaries
        );
    }
}
