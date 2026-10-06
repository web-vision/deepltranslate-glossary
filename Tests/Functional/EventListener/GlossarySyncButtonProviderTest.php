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
     * @todo TYPO3 v13 deprecates passing the icon size as string, which the
     *       provider still does as TYPO3 v12 knows no IconSize enum. Drop
     *       IgnoreDeprecations together with the support of TYPO3 v12.
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

        // The listener reads the request from $GLOBALS['TYPO3_REQUEST'] on TYPO3 v12 and v13.
        $event = new ModifyButtonBarEvent([], GeneralUtility::makeInstance(ButtonBar::class));
        $this->get(GlossarySyncButtonProvider::class)($event);

        return $event;
    }
}
