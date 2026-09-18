<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Configuration;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Configuration\SiteTcaConfiguration;
use WebVision\Deepltranslate\Glossary\Tests\Functional\AbstractDeepLTestCase;

final class SiteLanguageGlossaryTermsFieldTest extends AbstractDeepLTestCase
{
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
