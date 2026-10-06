<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use WebVision\Deepltranslate\Glossary\Controller\GlossarySyncController;
use WebVision\Deepltranslate\Glossary\Tests\Functional\AbstractDeepLTestCase;

/**
 * The synchronisation route can be called without the button, with any folder and any return
 * URL, so it has to check the request on its own.
 *
 * Folder 2 may be edited by every user mounting the root page, folder 5 only be shown.
 */
final class GlossarySyncControllerAccessTest extends AbstractDeepLTestCase
{
    use SiteBasedTestTrait;

    protected const LANGUAGE_PRESETS = [
        'DE' => [
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
                $this->buildDefaultLanguageConfiguration('DE', '/'),
                $this->buildLanguageConfiguration('EN-GB', '/en-gb/'),
            ],
        );
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/GlossarySyncPermission/glossaryFoldersAndEditors.csv');
    }

    #[Test]
    public function adminSynchronisesAnyGlossaryFolder(): void
    {
        $this->setUpBackendUser(1);

        $response = $this->get(GlossarySyncController::class)->update($this->buildRequest(5, '/typo3/module/web/list?id=5'));

        self::assertSame('/typo3/module/web/list?id=5', $response->getHeaderLine('location'));
        self::assertSame([ContextualFeedbackSeverity::OK], $this->getMessageSeverities());
        self::assertSynchronised(5);
    }

    #[Test]
    public function editorWithPermissionFolderAccessAndTermEditingSynchronises(): void
    {
        $this->setUpBackendUser(4);

        $response = $this->get(GlossarySyncController::class)->update($this->buildRequest(2, '/typo3/module/web/list?id=2'));

        self::assertSame('/typo3/module/web/list?id=2', $response->getHeaderLine('location'));
        self::assertSame([ContextualFeedbackSeverity::OK], $this->getMessageSeverities());
        self::assertSynchronised(2);
    }

    /**
     * @return \Generator<string, array{userId: int, pageId: int}>
     */
    public static function synchronisationNotAllowedDataProvider(): \Generator
    {
        yield 'editor without the glossary sync permission' => [
            'userId' => 2,
            'pageId' => 2,
        ];
        yield 'editor with the permission but without a mount of the folder' => [
            'userId' => 3,
            'pageId' => 2,
        ];
        yield 'editor with the permission but without the right to edit terms' => [
            'userId' => 5,
            'pageId' => 2,
        ];
        yield 'editor with the permission on one folder requesting a mounted folder without edit access' => [
            'userId' => 4,
            'pageId' => 5,
        ];
        yield 'admin requesting a page that does not exist' => [
            'userId' => 1,
            'pageId' => 4711,
        ];
    }

    #[Test]
    #[DataProvider('synchronisationNotAllowedDataProvider')]
    public function synchronisationIsDeniedWithoutPermission(int $userId, int $pageId): void
    {
        $this->setUpBackendUser($userId);

        $response = $this->get(GlossarySyncController::class)->update($this->buildRequest($pageId, '/typo3/module/web/list?id=' . $pageId));

        self::assertSame('/typo3/module/web/list?id=' . $pageId, $response->getHeaderLine('location'));
        $messages = $this->getMessages();
        self::assertCount(1, $messages);
        self::assertSame(ContextualFeedbackSeverity::ERROR, $messages[0]->getSeverity());
        self::assertSame(
            $this->get(LanguageServiceFactory::class)
                ->createFromUserPreferences($GLOBALS['BE_USER'])
                ->sL('LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossary.sync.message.denied'),
            $messages[0]->getMessage()
        );
        self::assertNotSynchronised($pageId);
    }

    #[Test]
    public function synchronisationIsDeniedWhileTheTermsAreReadOnly(): void
    {
        $this->setUpBackendUser(4);
        $GLOBALS['TCA']['tx_deepltranslate_glossaryentry']['ctrl']['readOnly'] = true;

        $this->get(GlossarySyncController::class)->update($this->buildRequest(2, '/typo3/module/web/list?id=2'));

        self::assertSame([ContextualFeedbackSeverity::ERROR], $this->getMessageSeverities());
        self::assertNotSynchronised(2);
    }

    /**
     * @return \Generator<string, array{uid: mixed}>
     */
    public static function invalidPageIdDataProvider(): \Generator
    {
        yield 'missing' => [
            'uid' => null,
        ];
        yield 'empty' => [
            'uid' => '',
        ];
        yield 'zero' => [
            'uid' => '0',
        ];
        yield 'negative' => [
            'uid' => '-5',
        ];
        yield 'not numeric' => [
            'uid' => 'abc',
        ];
        yield 'number followed by text' => [
            'uid' => '5abc',
        ];
        yield 'array' => [
            'uid' => ['5'],
        ];
    }

    #[Test]
    #[DataProvider('invalidPageIdDataProvider')]
    public function invalidPageIdIsRejectedBeforeAnythingElse(mixed $uid): void
    {
        $this->setUpBackendUser(1);
        $queryParameters = ['returnUrl' => '/typo3/module/web/list?id=5'];
        if ($uid !== null) {
            $queryParameters['uid'] = $uid;
        }
        $request = (new ServerRequest('https://localhost/typo3/glossary'))->withQueryParams($queryParameters);

        $response = $this->get(GlossarySyncController::class)->update($request);

        self::assertSame('/typo3/module/web/list?id=5', $response->getHeaderLine('location'));
        $messages = $this->getMessages();
        self::assertCount(1, $messages);
        self::assertSame('No ID given for glossary synchronization', $messages[0]->getMessage());
        self::assertNotSynchronised(5);
    }

    /**
     * @return \Generator<string, array{returnUrl: string}>
     */
    public static function returnUrlLeadingToAnotherHostDataProvider(): \Generator
    {
        yield 'absolute URL' => [
            'returnUrl' => 'https://attacker.example/phishing',
        ];
        yield 'protocol relative URL' => [
            'returnUrl' => '//attacker.example',
        ];
        // Browsers read a backslash as a slash.
        yield 'slash followed by a backslash' => [
            'returnUrl' => '/\\attacker.example',
        ];
        yield 'backslash followed by a slash' => [
            'returnUrl' => '\\/attacker.example',
        ];
        // Browsers drop a tab or line break from a URL, which turns the path into "//attacker.example".
        yield 'tab after the leading slash' => [
            'returnUrl' => "/\t/attacker.example",
        ];
        yield 'line feed after the leading slash' => [
            'returnUrl' => "/\n/attacker.example",
        ];
        yield 'carriage return after the leading slash' => [
            'returnUrl' => "/\r/attacker.example",
        ];
    }

    #[Test]
    #[DataProvider('returnUrlLeadingToAnotherHostDataProvider')]
    public function returnUrlLeadingToAnotherHostIsReplacedByTheRecordsOfTheFolder(string $returnUrl): void
    {
        $this->setUpBackendUser(1);

        $response = $this->get(GlossarySyncController::class)->update($this->buildRequest(2, $returnUrl));

        self::assertStringNotContainsString('attacker.example', $response->getHeaderLine('location'));
        self::assertSame($this->buildRecordsModuleUri(2), $response->getHeaderLine('location'));
        self::assertSynchronised(2);
    }

    #[Test]
    public function missingReturnUrlLeadsToTheRecordsOfTheFolder(): void
    {
        $this->setUpBackendUser(1);
        $request = (new ServerRequest('https://localhost/typo3/glossary'))->withQueryParams(['uid' => '2']);

        $response = $this->get(GlossarySyncController::class)->update($request);

        self::assertSame($this->buildRecordsModuleUri(2), $response->getHeaderLine('location'));
        self::assertSynchronised(2);
    }

    #[Test]
    public function returnUrlNotGivenAsStringLeadsToTheRecordsOfTheFolder(): void
    {
        $this->setUpBackendUser(1);
        $request = (new ServerRequest('https://localhost/typo3/glossary'))->withQueryParams([
            'uid' => '2',
            'returnUrl' => ['/typo3/'],
        ]);

        $response = $this->get(GlossarySyncController::class)->update($request);

        self::assertSame($this->buildRecordsModuleUri(2), $response->getHeaderLine('location'));
    }

    private function buildRequest(int $pageId, string $returnUrl): ServerRequest
    {
        return (new ServerRequest('https://localhost/typo3/glossary'))->withQueryParams([
            'uid' => (string)$pageId,
            'returnUrl' => $returnUrl,
        ]);
    }

    /**
     * A glossary folder is a sysfolder, whose terms only the list module shows.
     */
    private function buildRecordsModuleUri(int $pageId): string
    {
        return (string)$this->get(UriBuilder::class)->buildUriFromRoute('web_list', ['id' => $pageId]);
    }

    /**
     * @return list<FlashMessage>
     */
    private function getMessages(): array
    {
        return array_values($this->get(FlashMessageService::class)->getMessageQueueByIdentifier()->getAllMessages());
    }

    /**
     * @return list<ContextualFeedbackSeverity>
     */
    private function getMessageSeverities(): array
    {
        return array_map(
            static fn (FlashMessage $message): ContextualFeedbackSeverity => $message->getSeverity(),
            $this->getMessages()
        );
    }

    private function assertSynchronised(int $pageId): void
    {
        $glossaryIds = $this->getGlossaryIdsOfFolder($pageId);
        self::assertNotSame([], $glossaryIds);
        self::assertNotContains('', $glossaryIds);
    }

    private function assertNotSynchronised(int $pageId): void
    {
        self::assertSame([], $this->getGlossaryIdsOfFolder($pageId));
    }

    /**
     * @return list<string>
     */
    private function getGlossaryIdsOfFolder(int $pageId): array
    {
        return array_map(
            'strval',
            $this->get(ConnectionPool::class)
                ->getConnectionForTable('tx_deepltranslate_glossary')
                ->select(['glossary_id'], 'tx_deepltranslate_glossary', ['pid' => $pageId])
                ->fetchFirstColumn()
        );
    }
}
