<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use WebVision\Deepltranslate\Glossary\Service\GlossaryTermSanitizer;

final class GlossaryTermSanitizerTest extends UnitTestCase
{
    public static function byteLimitDataProvider(): \Generator
    {
        yield 'ASCII term of 1024 bytes' => [
            'term' => str_repeat('a', 1024),
            'exceeds' => false,
        ];
        yield 'ASCII term of 1025 bytes' => [
            'term' => str_repeat('a', 1025),
            'exceeds' => true,
        ];
        yield '512 umlauts take 1024 bytes' => [
            'term' => str_repeat('ü', 512),
            'exceeds' => false,
        ];
        yield '513 umlauts take 1026 bytes, below 1024 characters' => [
            'term' => str_repeat('ü', 513),
            'exceeds' => true,
        ];
        yield '257 emoji take 1028 bytes' => [
            'term' => str_repeat("\u{1F600}", 257),
            'exceeds' => true,
        ];
    }

    #[Test]
    #[DataProvider('byteLimitDataProvider')]
    public function exceedsByteLimitCountsUtf8Bytes(string $term, bool $exceeds): void
    {
        self::assertSame($exceeds, (new GlossaryTermSanitizer())->exceedsByteLimit($term));
    }
}
