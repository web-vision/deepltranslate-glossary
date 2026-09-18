<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use WebVision\Deepltranslate\Glossary\Controller\GlossarySyncController;
use WebVision\Deepltranslate\Glossary\Tests\Functional\AbstractDeepLTestCase;

final class GlossarySyncControllerTest extends AbstractDeepLTestCase
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
        'EN-US-PREFERRED' => [
            'id' => 2,
            'title' => 'English (US)',
            'locale' => 'en_US.UTF-8',
            'iso' => 'en',
            'hrefLang' => 'en-US',
            'direction' => '',
            'custom' => [
                'deeplTargetLanguage' => 'EN-US',
                'deeplGlossaryTerms' => 'preferred',
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

    public static function flashMessageDataProvider(): \Generator
    {
        yield 'unresolved collision of EN-GB and EN-US adds a warning' => [
            'usLanguage' => 'EN-US',
            'expectedSeverities' => [
                ContextualFeedbackSeverity::WARNING,
                ContextualFeedbackSeverity::OK,
            ],
        ];
        yield 'collision resolved by a preferred language adds no warning' => [
            'usLanguage' => 'EN-US-PREFERRED',
            'expectedSeverities' => [
                ContextualFeedbackSeverity::OK,
            ],
        ];
    }

    /**
     * @param non-empty-string $usLanguage
     * @param list<ContextualFeedbackSeverity> $expectedSeverities
     */
    #[Test]
    #[DataProvider('flashMessageDataProvider')]
    public function updateReportsUnresolvedLanguageCodeCollisions(string $usLanguage, array $expectedSeverities): void
    {
        $this->writeSiteConfiguration(
            identifier: 'acme',
            site: $this->buildSiteConfiguration(rootPageId: 1),
            languages: [
                $this->buildDefaultLanguageConfiguration('DE', '/'),
                $this->buildLanguageConfiguration('EN-GB', '/en-gb/'),
                $this->buildLanguageConfiguration($usLanguage, '/en-us/'),
            ],
        );
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/SharedLanguageCode/sharedLanguageCodeEnglishVariants.csv');
        $this->setUpBackendUser(1);
        $request = (new ServerRequest('https://example.com/typo3/glossary/sync'))->withQueryParams([
            'uid' => '2',
            'returnUrl' => '/typo3/',
        ]);

        $this->get(GlossarySyncController::class)->update($request);

        $messages = $this->get(FlashMessageService::class)->getMessageQueueByIdentifier()->getAllMessages();
        self::assertSame($expectedSeverities, array_map(
            static fn (FlashMessage $message): ContextualFeedbackSeverity => $message->getSeverity(),
            $messages
        ));
        if (count($expectedSeverities) > 1) {
            self::assertSame(
                'Glossary folder 2: language code "en" is shared by several site languages',
                $messages[0]->getTitle()
            );
        }
    }
}
