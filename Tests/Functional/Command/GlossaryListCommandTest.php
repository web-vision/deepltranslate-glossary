<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Command;

use DeepL\MultilingualGlossaryDictionaryEntries;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use WebVision\Deepltranslate\Glossary\Client\GlossaryAPIV3ClientInterface;
use WebVision\Deepltranslate\Glossary\Command\GlossaryListCommand;
use WebVision\Deepltranslate\Glossary\Tests\Functional\AbstractDeepLTestCase;

final class GlossaryListCommandTest extends AbstractDeepLTestCase
{
    #[Test]
    public function deletedGlossaryIsReportedAsNotFound(): void
    {
        $client = $this->get(GlossaryAPIV3ClientInterface::class);
        $glossaryId = $client->createGlossary(
            'Deleted glossary',
            [
                new MultilingualGlossaryDictionaryEntries('en', 'de', ['proton beam' => 'Protonenstrahl']),
            ]
        )->glossaryId;
        $client->deleteGlossary($glossaryId);
        $commandTester = new CommandTester($this->get(GlossaryListCommand::class));

        $exitCode = $commandTester->execute(['glossary_id' => $glossaryId]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString(
            sprintf('Glossary "%s" not found.', $glossaryId),
            $commandTester->getDisplay()
        );
    }
}
