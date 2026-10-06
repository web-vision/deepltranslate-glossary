<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\EventListener;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Module\ModuleProvider;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\Components\Buttons\LinkButton;
use TYPO3\CMS\Backend\Template\Components\ModifyButtonBarEvent;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use WebVision\Deepltranslate\Glossary\EventListener\GlossarySyncButtonProvider;
use WebVision\Deepltranslate\Glossary\Tests\Functional\AbstractDeepLTestCase;

/**
 * The synchronisation button is offered for the same pages the
 * synchronisation route accepts: a sysfolder (doktype 254) with module
 * `glossary`.
 */
final class GlossarySyncButtonProviderTest extends AbstractDeepLTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/GlossaryFolderGuard/glossaryFolderGuard.csv');
        $this->setUpBackendUser(1);
    }

    public static function pageNotSetUpAsGlossaryDataProvider(): \Generator
    {
        yield 'sysfolder without module glossary' => [
            'pageUid' => 3,
        ];
        yield 'standard page with module glossary' => [
            'pageUid' => 4,
        ];
        yield 'deleted glossary folder' => [
            'pageUid' => 5,
        ];
    }

    #[Test]
    #[DataProvider('pageNotSetUpAsGlossaryDataProvider')]
    public function buttonIsNotAddedForPageNotSetUpAsGlossary(int $pageUid): void
    {
        $event = $this->dispatchForPage($pageUid);

        self::assertSame([], $event->getButtons());
    }

    /**
     * @todo TYPO3 v14 deprecates ButtonBar::makeLinkButton(), which the
     *       provider still calls to build the button. Drop IgnoreDeprecations
     *       once the provider builds the button with the ComponentFactory
     *       where the core provides it.
     */
    #[Test]
    #[IgnoreDeprecations]
    public function buttonIsAddedForFolderSetUpAsGlossary(): void
    {
        $event = $this->dispatchForPage(2);

        $button = $event->getButtons()[ButtonBar::BUTTON_POSITION_LEFT][5][0] ?? null;
        self::assertInstanceOf(LinkButton::class, $button);
        self::assertStringContainsString('uid=2', $button->getHref());
    }

    private function dispatchForPage(int $pageUid): ModifyButtonBarEvent
    {
        $request = (new ServerRequest('https://localhost/typo3/module/web/layout?id=' . $pageUid))
            ->withQueryParams(['id' => (string)$pageUid])
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('module', $this->get(ModuleProvider::class)->getModule('web_layout'));
        $request = $request->withAttribute('normalizedParams', NormalizedParams::createFromRequest($request));
        $GLOBALS['TYPO3_REQUEST'] = $request;

        // The event takes the request as third argument where the core passes
        // it, and ignores it where the listener reads $GLOBALS['TYPO3_REQUEST'].
        $event = (new \ReflectionClass(ModifyButtonBarEvent::class))->newInstanceArgs([
            [],
            GeneralUtility::makeInstance(ButtonBar::class),
            $request,
        ]);
        $this->get(GlossarySyncButtonProvider::class)($event);

        return $event;
    }
}
