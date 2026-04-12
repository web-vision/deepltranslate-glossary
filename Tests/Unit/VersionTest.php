<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class VersionTest extends UnitTestCase
{
    private const ALLOWED_MAJOR_VERSIONS = [13, 14];

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
