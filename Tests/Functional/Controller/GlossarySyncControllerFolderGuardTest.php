<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use WebVision\Deepltranslate\Glossary\Controller\GlossarySyncController;
use WebVision\Deepltranslate\Glossary\Tests\Functional\AbstractDeepLTestCase;

/**
 * The synchronisation route accepts only a folder set up as glossary:
 * a sysfolder (doktype 254) with module `glossary`.
 */
final class GlossarySyncControllerFolderGuardTest extends AbstractDeepLTestCase
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
            'locale' => 'de_DE.UTF-8',
            'iso' => 'de',
            'hrefLang' => 'de-DE',
            'direction' => '',
            'custom' => [
                'deeplTargetLanguage' => 'DE',
            ],
        ],
    ];

    protected array $testExtensionsToLoad = [
        'web-vision/deeplcom-deepl-php',
        'web-vision/deepl-base',
        'web-vision/deepltranslate-core',
        'web-vision/deepltranslate-glossary',
        __DIR__ . '/../Fixtures/Extensions/test_services_override',
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
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/GlossaryFolderGuard/glossaryFolderGuard.csv');
        $this->setUpBackendUser(1);
    }

    public static function pageNotSetUpAsGlossaryDataProvider(): \Generator
    {
        yield 'sysfolder without module glossary' => [
            'pageUid' => 3,
        ];
        yield 'standard page with module glossary' => [
            'pageUid' => 4,
        ];
    }

    #[Test]
    #[DataProvider('pageNotSetUpAsGlossaryDataProvider')]
    public function pageNotSetUpAsGlossaryIsRejected(int $pageUid): void
    {
        $returnUrl = '/typo3/module/web/layout?id=' . $pageUid;
        $request = (new ServerRequest('https://localhost/typo3/glossary'))
            ->withQueryParams([
                'uid' => (string)$pageUid,
                'returnUrl' => $returnUrl,
            ]);

        $response = $this->get(GlossarySyncController::class)->update($request);

        self::assertSame($returnUrl, $response->getHeaderLine('location'));
        $messages = $this->get(FlashMessageService::class)->getMessageQueueByIdentifier()->getAllMessages();
        self::assertCount(1, $messages);
        self::assertSame(ContextualFeedbackSeverity::ERROR, $messages[0]->getSeverity());
        self::assertSame(
            sprintf('Page "%d" not configured for glossary synchronization.', $pageUid),
            $messages[0]->getMessage()
        );
        self::assertSame(0, $this->countGlossariesOnPage($pageUid));
    }

    public static function pageNotAccessibleDataProvider(): \Generator
    {
        yield 'deleted glossary folder' => [
            'pageUid' => 5,
        ];
        yield 'page that does not exist' => [
            'pageUid' => 99,
        ];
    }

    /**
     * The permission check comes before the folder check and reads the page with the page
     * permissions of the user, so a deleted or missing page is rejected as not accessible.
     */
    #[Test]
    #[DataProvider('pageNotAccessibleDataProvider')]
    public function pageNotAccessibleIsRejected(int $pageUid): void
    {
        $returnUrl = '/typo3/module/web/layout?id=' . $pageUid;
        $request = (new ServerRequest('https://localhost/typo3/glossary'))
            ->withQueryParams([
                'uid' => (string)$pageUid,
                'returnUrl' => $returnUrl,
            ]);

        $response = $this->get(GlossarySyncController::class)->update($request);

        self::assertSame($returnUrl, $response->getHeaderLine('location'));
        $messages = $this->get(FlashMessageService::class)->getMessageQueueByIdentifier()->getAllMessages();
        self::assertCount(1, $messages);
        self::assertSame(ContextualFeedbackSeverity::ERROR, $messages[0]->getSeverity());
        self::assertSame(
            $this->get(LanguageServiceFactory::class)
                ->createFromUserPreferences($GLOBALS['BE_USER'])
                ->sL('LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossary.sync.message.denied'),
            $messages[0]->getMessage()
        );
        self::assertSame(0, $this->countGlossariesOnPage($pageUid));
    }

    #[Test]
    public function folderSetUpAsGlossaryIsSynchronised(): void
    {
        $request = (new ServerRequest('https://localhost/typo3/glossary'))
            ->withQueryParams([
                'uid' => '2',
                'returnUrl' => '/typo3/module/web/layout?id=2',
            ]);

        $this->get(GlossarySyncController::class)->update($request);

        $messages = $this->get(FlashMessageService::class)->getMessageQueueByIdentifier()->getAllMessages();
        self::assertCount(1, $messages);
        self::assertSame(ContextualFeedbackSeverity::OK, $messages[0]->getSeverity());
        self::assertSame(1, $this->countGlossariesOnPage(2));
    }

    private function countGlossariesOnPage(int $pageUid): int
    {
        return $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_deepltranslate_glossary')
            ->count('*', 'tx_deepltranslate_glossary', ['pid' => $pageUid]);
    }
}
