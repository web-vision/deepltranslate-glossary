<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Service;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\DependencyInjection\Container;
use TYPO3\CMS\Core\EventDispatcher\ListenerProvider;
use WebVision\Deepltranslate\Glossary\Event\ModifyGlossaryNameEvent;
use WebVision\Deepltranslate\Glossary\Service\GlossaryNameService;
use WebVision\Deepltranslate\Glossary\Tests\Functional\AbstractDeepLTestCase;

final class GlossaryNameServiceTest extends AbstractDeepLTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/glossaryFolder.csv');
    }

    #[Test]
    public function glossaryIsNamedAfterItsFolder(): void
    {
        $subject = $this->get(GlossaryNameService::class);

        self::assertSame('Glossary [2]', $subject->getGlossaryName(2));
    }

    #[Test]
    public function folderWithoutTitleGetsAGenericName(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/untitledGlossaryFolder.csv');
        $subject = $this->get(GlossaryNameService::class);

        self::assertSame('Glossary [7]', $subject->getGlossaryName(7));
    }

    #[Test]
    public function listenerChangesTheName(): void
    {
        $this->registerNameListener(static function (ModifyGlossaryNameEvent $event): void {
            $event->glossaryName = sprintf('ACME %s (%d)', $event->folderTitle, $event->pageId);
        });
        $subject = $this->get(GlossaryNameService::class);

        self::assertSame('ACME Glossary (2)', $subject->getGlossaryName(2));
    }

    #[Test]
    public function emptyNameOfAListenerFallsBackToTheDefaultName(): void
    {
        // DeepL refuses a glossary without a name, so an empty name would fail every sync.
        $this->registerNameListener(static function (ModifyGlossaryNameEvent $event): void {
            $event->glossaryName = '  ';
        });
        $subject = $this->get(GlossaryNameService::class);

        self::assertSame('Glossary [2]', $subject->getGlossaryName(2));
    }

    #[Test]
    public function nameOfAListenerAboveTheByteLimitIsCutOnACharacterBoundary(): void
    {
        // An umlaut takes two UTF-8 bytes, the cut must not split the one crossing 1024 bytes.
        $this->registerNameListener(static function (ModifyGlossaryNameEvent $event): void {
            $event->glossaryName = 'a' . str_repeat('ü', 600);
        });
        $subject = $this->get(GlossaryNameService::class);

        self::assertSame('a' . str_repeat('ü', 511), $subject->getGlossaryName(2));
    }

    #[Test]
    public function storedNameIsKept(): void
    {
        $subject = $this->get(GlossaryNameService::class);

        self::assertSame('ACME glossary', $subject->fitStoredName(' ACME glossary ', 2));
    }

    #[Test]
    public function blankStoredNameIsReplacedByTheNameOfTheFolder(): void
    {
        // DeepL refuses to create a glossary without a name.
        $subject = $this->get(GlossaryNameService::class);

        self::assertSame('Glossary [2]', $subject->fitStoredName('  ', 2));
    }

    #[Test]
    public function storedNameAboveTheByteLimitIsCutOnACharacterBoundary(): void
    {
        $subject = $this->get(GlossaryNameService::class);

        self::assertSame('a' . str_repeat('ü', 511), $subject->fitStoredName('a' . str_repeat('ü', 600), 2));
    }

    #[Test]
    public function storedNameOfExactlyTheByteLimitIsKept(): void
    {
        $subject = $this->get(GlossaryNameService::class);

        self::assertSame(str_repeat('ü', 512), $subject->fitStoredName(str_repeat('ü', 512), 2));
    }

    private function registerNameListener(\Closure $listener): void
    {
        /** @var Container $container */
        $container = $this->getContainer();
        $container->set('glossary-name-listener', $listener);
        $this->get(ListenerProvider::class)->addListener(ModifyGlossaryNameEvent::class, 'glossary-name-listener');
    }
}
