<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Configuration;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Configuration\SiteTcaConfiguration;
use WebVision\Deepltranslate\Glossary\Tests\Functional\AbstractDeepLTestCase;

final class SiteLanguageGlossaryTermsFieldTest extends AbstractDeepLTestCase
{
    protected array $testExtensionsToLoad = [
        'web-vision/deeplcom-deepl-php',
        'web-vision/deepl-base',
        'web-vision/deepltranslate-core',
        'web-vision/deepltranslate-glossary',
        __DIR__ . '/../Fixtures/Extensions/test_services_override',
    ];

    #[Test]
    public function glossaryTermsFieldIsAddedToTheDeepLPaletteOfSiteLanguages(): void
    {
        $tca = (new SiteTcaConfiguration())->getTca();

        self::assertSame(
            [
                'automatic',
                'preferred',
            ],
            array_column($tca['site_language']['columns']['deeplGlossaryTerms']['config']['items'], 'value')
        );
        self::assertSame(
            'deeplTargetLanguage, deeplFormality, --linebreak--, deeplGlossaryTerms',
            $tca['site_language']['palettes']['deepl']['showitem']
        );
    }
}
