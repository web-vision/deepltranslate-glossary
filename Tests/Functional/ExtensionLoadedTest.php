<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

final class ExtensionLoadedTest extends AbstractDeepLTestCase
{
    private const ALLOWED_MAJOR_VERSIONS = [13, 14];

    public static function loadedExtensionsDataSet(): \Generator
    {
        $packages = [
            'deepltranslate_core' => 'web-vision/deepltranslate-core',
            'deepltranslate_glossary' => 'web-vision/deepltranslate-glossary',
            'deepl_base' => 'web-vision/deepl-base',
            'deeplcom_deeplphp' => 'web-vision/deeplcom-deepl-php',
        ];
        foreach ($packages as $extensionKey => $packageName) {
            yield 'EXT:' . $extensionKey => ['identifier' => $extensionKey];
            yield $packageName => ['identifier' => $packageName];
        }
    }

    #[DataProvider('loadedExtensionsDataSet')]
    #[Test]
    public function isLoadedExtensionKey(string $identifier): void
    {
        $this->assertTrue(ExtensionManagementUtility::isLoaded($identifier), $identifier);
    }

    #[Test]
    public function allowedMajorTypo3Version(): void
    {
        $this->assertContains((new Typo3Version())->getMajorVersion(), self::ALLOWED_MAJOR_VERSIONS);
    }

    #[Group('not-core-14')]
    #[Test]
    public function verifyCore13(): void
    {
        $this->markTestSkipped('Needs phpunit update first');
        //$this->assertSame(13, (new Typo3Version())->getMajorVersion());
    }

    #[Group('not-core-13')]
    #[Test]
    public function verifyCore14(): void
    {
        $this->markTestSkipped('Needs phpunit update first');
        //$this->assertSame(14, (new Typo3Version())->getMajorVersion());
    }
}
