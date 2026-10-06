<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Unit\Domain\Dto;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use WebVision\Deepltranslate\Glossary\Domain\Dto\Glossary;

final class GlossaryTest extends UnitTestCase
{
    #[Test]
    public function glossaryIdOfReleasedVersionsMayBeNull(): void
    {
        // Released versions left the column nullable.
        $glossary = Glossary::fromDatabase([
            'uid' => '3',
            'glossary_id' => null,
            'glossary_name' => 'Glossary [2]',
            'glossary_lastsync' => 0,
            'glossary_ready' => null,
        ]);

        self::assertSame(3, $glossary->uid);
        self::assertSame('', $glossary->glossaryId);
        self::assertSame('Glossary [2]', $glossary->name);
        self::assertFalse($glossary->ready);
    }
}
