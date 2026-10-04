<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Access;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use WebVision\Deepltranslate\Glossary\Access\GlossarySyncPermission;
use WebVision\Deepltranslate\Glossary\Tests\Functional\AbstractDeepLTestCase;

/**
 * The synchronisation button and the synchronisation route ask the same question, so both have
 * to get the same answer.
 */
final class GlossarySyncPermissionTest extends AbstractDeepLTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Service/Fixtures/glossaryFolder.csv');
        $this->importCSVDataSet(__DIR__ . '/../Controller/Fixtures/editors.csv');
        $this->get(ConnectionPool::class)
            ->getConnectionForTable('pages')
            ->update('pages', ['perms_everybody' => 31], ['pid' => 0]);
        $this->get(ConnectionPool::class)
            ->getConnectionForTable('pages')
            ->update('pages', ['perms_everybody' => 31], ['pid' => 1]);
    }

    public static function backendUserDataProvider(): \Generator
    {
        yield 'admin' => [
            'userId' => 1,
            'expected' => true,
        ];
        yield 'editor with the permission, access to the folder and the right to edit terms' => [
            'userId' => 4,
            'expected' => true,
        ];
        yield 'editor without the glossary sync permission' => [
            'userId' => 2,
            'expected' => false,
        ];
        yield 'editor with the permission but without access to the folder' => [
            'userId' => 3,
            'expected' => false,
        ];
        yield 'editor with the permission but without the right to edit terms' => [
            'userId' => 5,
            'expected' => false,
        ];
    }

    #[Test]
    #[DataProvider('backendUserDataProvider')]
    public function synchronisationIsGrantedOnlyWithPermissionAndAccess(int $userId, bool $expected): void
    {
        $backendUser = $this->setUpBackendUser($userId);
        $subject = $this->get(GlossarySyncPermission::class);

        self::assertSame($expected, $subject->isGranted($backendUser, 2));
    }

    #[Test]
    public function synchronisationIsDeniedWhileTheTermsAreReadOnly(): void
    {
        $backendUser = $this->setUpBackendUser(4);
        $GLOBALS['TCA']['tx_deepltranslate_glossaryentry']['ctrl']['readOnly'] = true;
        $subject = $this->get(GlossarySyncPermission::class);

        self::assertFalse($subject->isGranted($backendUser, 2));
    }
}
