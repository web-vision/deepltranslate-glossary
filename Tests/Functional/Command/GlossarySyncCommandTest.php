<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use WebVision\Deepltranslate\Glossary\Command\GlossarySyncCommand;
use WebVision\Deepltranslate\Glossary\Tests\Functional\AbstractDeepLTestCase;

final class GlossarySyncCommandTest extends AbstractDeepLTestCase
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

    public static function warningDataProvider(): \Generator
    {
        yield 'unresolved collision of EN-GB and EN-US is reported' => [
            'usLanguage' => 'EN-US',
            'expectWarning' => true,
        ];
        yield 'collision resolved by a preferred language stays silent' => [
            'usLanguage' => 'EN-US-PREFERRED',
            'expectWarning' => false,
        ];
    }

    /**
     * @param non-empty-string $usLanguage
     */
    #[Test]
    #[DataProvider('warningDataProvider')]
    public function syncReportsUnresolvedLanguageCodeCollisions(string $usLanguage, bool $expectWarning): void
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

        $commandTester = new CommandTester($this->get(GlossarySyncCommand::class));
        $commandTester->execute([
            '--pageId' => 2,
        ]);

        $output = (string)preg_replace('/\s+/', ' ', $commandTester->getDisplay());
        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $expectedWarning = '[WARNING] Glossary folder 2: language code "en" is shared by several site languages';
        if ($expectWarning) {
            self::assertStringContainsString($expectedWarning, $output);
            self::assertStringContainsString('The terms of "English (UK)" [1] are used', $output);
            return;
        }
        self::assertStringNotContainsString('[WARNING]', $output);
    }
}
