<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Unit\Access;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use WebVision\Deepltranslate\Core\Access\AccessItemInterface;
use WebVision\Deepltranslate\Glossary\Access\AllowedGlossarySyncAccess;

class AllowedGlossarySyncAccessTest extends UnitTestCase
{
    private AllowedGlossarySyncAccess $accessInstance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accessInstance = new AllowedGlossarySyncAccess();
    }

    #[Test]
    public function hasInterfaceImplementation(): void
    {
        self::assertInstanceOf(AccessItemInterface::class, $this->accessInstance);
    }

    #[Test]
    public function getIdentifier(): void
    {
        self::assertSame('allowedGlossarySync', $this->accessInstance->getIdentifier());
    }

    #[Test]
    public function getTitle(): void
    {
        self::assertIsString($this->accessInstance->getTitle());
    }

    #[Test]
    public function getDescription(): void
    {
        self::assertIsString($this->accessInstance->getDescription());
    }

    #[Test]
    public function getIconIdentifier(): void
    {
        self::assertSame('deepl-logo', $this->accessInstance->getIconIdentifier());
    }
}
