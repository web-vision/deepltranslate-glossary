<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Access;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebVision\Deepltranslate\Glossary\Access\GlossarySyncPermission;
use WebVision\Deepltranslate\Glossary\Tests\Functional\AbstractDeepLTestCase;

/**
 * The synchronisation button and the synchronisation route ask the same question, so both have
 * to get the same answer.
 *
 * Folder 2 may be edited by every user mounting the root page, folder 5 only be shown.
 */
final class GlossarySyncPermissionTest extends AbstractDeepLTestCase
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

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/GlossarySyncPermission/glossaryFoldersAndEditors.csv');
    }

    /**
     * @return \Generator<string, array{userId: int, pageId: int, expected: bool}>
     */
    public static function backendUserDataProvider(): \Generator
    {
        yield 'admin' => [
            'userId' => 1,
            'pageId' => 2,
            'expected' => true,
        ];
        yield 'admin on a folder others may only show' => [
            'userId' => 1,
            'pageId' => 5,
            'expected' => true,
        ];
        yield 'admin on a page that does not exist' => [
            'userId' => 1,
            'pageId' => 4711,
            'expected' => false,
        ];
        yield 'editor with the permission, a mount of the folder and the right to edit terms' => [
            'userId' => 4,
            'pageId' => 2,
            'expected' => true,
        ];
        yield 'editor without the glossary sync permission' => [
            'userId' => 2,
            'pageId' => 2,
            'expected' => false,
        ];
        yield 'editor with the permission but without a mount of the folder' => [
            'userId' => 3,
            'pageId' => 2,
            'expected' => false,
        ];
        yield 'editor with the permission but without the right to edit terms' => [
            'userId' => 5,
            'pageId' => 2,
            'expected' => false,
        ];
        yield 'editor with the permission on a mounted folder without edit access' => [
            'userId' => 4,
            'pageId' => 5,
            'expected' => false,
        ];
    }

    #[Test]
    #[DataProvider('backendUserDataProvider')]
    public function synchronisationIsGrantedOnlyWithPermissionAndAccess(int $userId, int $pageId, bool $expected): void
    {
        $backendUser = $this->setUpBackendUser($userId);

        self::assertSame($expected, $this->get(GlossarySyncPermission::class)->isGranted($backendUser, $pageId));
    }

    #[Test]
    public function synchronisationIsDeniedWhileTheTermsAreReadOnly(): void
    {
        $backendUser = $this->setUpBackendUser(4);
        $GLOBALS['TCA']['tx_deepltranslate_glossaryentry']['ctrl']['readOnly'] = true;

        self::assertFalse($this->get(GlossarySyncPermission::class)->isGranted($backendUser, 2));
    }
}
