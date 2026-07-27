<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Domain\Repository;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use WebVision\Deepltranslate\Glossary\Domain\Repository\GlossaryRepository;
use WebVision\Deepltranslate\Glossary\Tests\Functional\AbstractDeepLTestCase;

/**
 * The repositories were created through GeneralUtility::makeInstance() before they got
 * dependencies, so code of other extensions doing so has to keep working.
 */
final class RepositoryInstantiationTest extends AbstractDeepLTestCase
{
    /**
     * @param class-string $className
     */
    #[Test]
    #[DataProvider('repositoryDataProvider')]
    public function repositoryCanBeCreatedThroughMakeInstance(string $className): void
    {
        self::assertInstanceOf($className, GeneralUtility::makeInstance($className));
    }

    public static function repositoryDataProvider(): \Generator
    {
        yield 'glossary repository' => [
            'className' => GlossaryRepository::class,
        ];
    }
}
