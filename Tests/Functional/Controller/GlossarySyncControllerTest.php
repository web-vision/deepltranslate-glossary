<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Controller;

use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use WebVision\Deepltranslate\Glossary\Controller\GlossarySyncController;
use WebVision\Deepltranslate\Glossary\Service\MultilingualGlossaryService;
use WebVision\Deepltranslate\Glossary\Tests\Functional\AbstractDeepLTestCase;

final class GlossarySyncControllerTest extends AbstractDeepLTestCase
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

        $this->importCSVDataSet(__DIR__ . '/../Service/Fixtures/glossaryFolder.csv');
        $this->setUpBackendUser(1);
    }

    #[Test]
    public function failingSynchronisationIsReportedAsFlashMessage(): void
    {
        // DeepL refuses to create a glossary without a name, which makes the synchronisation fail.
        $this->importCSVDataSet(__DIR__ . '/Fixtures/unnamedGlossary.csv');
        $request = (new ServerRequest('https://localhost/typo3/glossary/sync'))
            ->withQueryParams([
                'uid' => '2',
                'returnUrl' => '/typo3/module/web/list?id=2',
            ]);

        $response = $this->get(GlossarySyncController::class)->update($request);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/typo3/module/web/list?id=2', $response->getHeaderLine('location'));
        $messages = $this->get(FlashMessageService::class)->getMessageQueueByIdentifier()->getAllMessages();
        self::assertCount(1, $messages);
        self::assertSame(ContextualFeedbackSeverity::ERROR, $messages[0]->getSeverity());
    }

    #[Test]
    public function failingRequestToDeeplPointsAtTheConnectionToDeepl(): void
    {
        // DeepL refuses to create a glossary without a name, which makes the synchronisation fail.
        $this->importCSVDataSet(__DIR__ . '/Fixtures/unnamedGlossary.csv');
        $request = (new ServerRequest('https://localhost/typo3/glossary/sync'))
            ->withQueryParams([
                'uid' => '2',
                'returnUrl' => '/typo3/module/web/list?id=2',
            ]);

        $this->get(GlossarySyncController::class)->update($request);

        // The terms are fine, so the message must not send the editor to look at them.
        $messages = $this->get(FlashMessageService::class)->getMessageQueueByIdentifier()->getAllMessages();
        self::assertCount(1, $messages);
        self::assertNotSame('', $messages[0]->getMessage());
        self::assertSame(
            $this->get(LanguageServiceFactory::class)
                ->createFromUserPreferences($GLOBALS['BE_USER'])
                ->sL('LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossary.sync.message.failed'),
            $messages[0]->getMessage()
        );
    }

    #[Test]
    public function synchronisingAFolderWithoutTermsReportsTheRemovedGlossary(): void
    {
        $this->get(MultilingualGlossaryService::class)->syncGlossary(2);
        $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_deepltranslate_glossaryentry')
            ->delete('tx_deepltranslate_glossaryentry', ['pid' => 2]);
        $request = (new ServerRequest('https://localhost/typo3/glossary/sync'))
            ->withQueryParams([
                'uid' => '2',
                'returnUrl' => '/typo3/module/web/list?id=2',
            ]);

        $this->get(GlossarySyncController::class)->update($request);

        // Translations no longer use a glossary, which the editor must not read as ready to use.
        $messages = $this->get(FlashMessageService::class)->getMessageQueueByIdentifier()->getAllMessages();
        self::assertCount(1, $messages);
        self::assertSame(ContextualFeedbackSeverity::INFO, $messages[0]->getSeverity());
        self::assertNotSame('', $messages[0]->getMessage());
        self::assertSame(
            $this->get(LanguageServiceFactory::class)
                ->createFromUserPreferences($GLOBALS['BE_USER'])
                ->sL('LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossary.sync.message.removed'),
            $messages[0]->getMessage()
        );
    }

    #[Test]
    public function folderNotConfiguredAsGlossaryIsRejected(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/plainSysfolder.csv');
        $request = (new ServerRequest('https://localhost/typo3/glossary/sync'))
            ->withQueryParams([
                'uid' => '5',
                'returnUrl' => '/typo3/module/web/list?id=5',
            ]);

        $this->get(GlossarySyncController::class)->update($request);

        $messages = $this->get(FlashMessageService::class)->getMessageQueueByIdentifier()->getAllMessages();
        self::assertCount(1, $messages);
        self::assertSame(ContextualFeedbackSeverity::ERROR, $messages[0]->getSeverity());
    }

    #[Test]
    public function glossaryFolderOutsideAnySiteIsReportedAsFlashMessage(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Service/Fixtures/glossaryFolderOutsideAnySite.csv');
        $request = (new ServerRequest('https://localhost/typo3/glossary/sync'))
            ->withQueryParams([
                'uid' => '21',
                'returnUrl' => '/typo3/module/web/list?id=21',
            ]);

        $response = $this->get(GlossarySyncController::class)->update($request);

        self::assertSame('/typo3/module/web/list?id=21', $response->getHeaderLine('location'));
        $messages = $this->get(FlashMessageService::class)->getMessageQueueByIdentifier()->getAllMessages();
        self::assertCount(1, $messages);
        self::assertSame(ContextualFeedbackSeverity::ERROR, $messages[0]->getSeverity());
    }
}
