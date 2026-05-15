<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Service;

use DeepL\DeepLException;
use DeepL\MultilingualGlossaryDictionaryEntries;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebVision\Deepltranslate\Glossary\Service\MultilingualGlossaryService;
use WebVision\Deepltranslate\Glossary\Tests\Functional\AbstractDeepLTestCase;

final class MultilingualGlossaryServiceTest extends AbstractDeepLTestCase
{
    /**
     * @return \Generator<string, array{
     *     sourceLanguage: non-empty-string,
     *     targetLanguage: non-empty-string,
     *     dictionaryEntries: array<string, string>,
     *     expectedEntryCount: int,
     * }>
     */
    public static function dictionaryCreator(): \Generator
    {
        yield 'EN - DE Dictionary' => [
            'sourceLanguage' => 'en',
            'targetLanguage' => 'de',
            'dictionaryEntries' => [
                'University of Applied Sciences' => 'Fachhochschule',
            ],
            'expectedEntryCount' => 1,
        ];
        yield 'DE-EN Dictionary' => [
            'sourceLanguage' => 'de',
            'targetLanguage' => 'en',
            'dictionaryEntries' => [
                'Hallo' => 'Hello',
                'Fachhochschule' => 'University of Applied Sciences',
            ],
            'expectedEntryCount' => 2,
        ];
    }

    /**
     * @param non-empty-string $sourceLanguage
     * @param non-empty-string$targetLanguage
     * @param array<string, string> $dictionaryEntries
     */
    #[Test]
    #[DataProvider('dictionaryCreator')]
    public function dictionaryIsCreated(
        string $sourceLanguage,
        string $targetLanguage,
        array $dictionaryEntries,
        int $expectedEntryCount,
    ): void {
        /** @var MultilingualGlossaryService $subject */
        $subject = $this->get(MultilingualGlossaryService::class);
        $dictionary = $subject->createDictionary(
            $sourceLanguage,
            $targetLanguage,
            $dictionaryEntries
        );
        $this->assertInstanceOf(MultilingualGlossaryDictionaryEntries::class, $dictionary);
        $this->assertIsArray($dictionary->entries);
        $this->assertCount($expectedEntryCount, $dictionary->entries);
    }

    /**
     * @return \Generator<string, array{
     *     sourceLanguage: non-empty-string,
     *     targetLanguage: non-empty-string,
     *     dictionaryEntries: array<string, string>,
     *     expectedException: class-string<\Throwable>,
     *     expectedExceptionMessage: string
     * }>
     */
    public static function dictionaryWithInvalidEntries(): \Generator
    {
        yield 'EN - DE Dictionary with empty entries' => [
            'sourceLanguage' => 'en',
            'targetLanguage' => 'de',
            'dictionaryEntries' => [],
            'expectedException' => DeepLException::class,
            'expectedExceptionMessage' => 'Input contains no entries',
        ];

        yield 'EN - DE Dictionary with whitespace only source entry' => [
            'sourceLanguage' => 'en',
            'targetLanguage' => 'de',
            'dictionaryEntries' => [
                '      ' => 'Ziel',
            ],
            'expectedException' => DeepLException::class,
            'expectedExceptionMessage' => 'Term "      " contains no non-whitespace characters',
        ];

        yield 'EN - DE Dictionary with whitespace only target entry' => [
            'sourceLanguage' => 'en',
            'targetLanguage' => 'de',
            'dictionaryEntries' => [
                'source' => '      ',
            ],
            'expectedException' => DeepLException::class,
            'expectedExceptionMessage' => 'Term "      " contains no non-whitespace characters',
        ];
    }

    /**
     * @param non-empty-string $sourceLanguage
     * @param non-empty-string $targetLanguage
     * @param array<string, string> $dictionaryEntries
     * @param class-string<\Throwable> $expectedException
     */
    #[Test]
    #[DataProvider('dictionaryWithInvalidEntries')]
    public function invalidEntriesCreateException(
        string $sourceLanguage,
        string $targetLanguage,
        array $dictionaryEntries,
        string $expectedException,
        string $expectedExceptionMessage,
    ): void {
        $this->expectException($expectedException);
        $this->expectExceptionMessage($expectedExceptionMessage);
        $subject = $this->get(MultilingualGlossaryService::class);
        $subject->createDictionary(
            $sourceLanguage,
            $targetLanguage,
            $dictionaryEntries
        );
    }
}
