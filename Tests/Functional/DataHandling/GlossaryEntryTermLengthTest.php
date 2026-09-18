<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\DataHandling;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use WebVision\Deepltranslate\Glossary\Tests\Functional\AbstractDeepLTestCase;

final class GlossaryEntryTermLengthTest extends AbstractDeepLTestCase
{
    /**
     * Mixes two, three and four byte UTF-8 characters, so a column or limit counting bytes
     * instead of characters, or a character set not covering four byte characters, fails.
     */
    private const MULTIBYTE_PATTERN = 'äöü陽子ビーム😀';

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/glossaryFolder.csv');
        $this->setUpBackendUser(1);
    }

    /**
     * @return \Generator<string, array{term: string}>
     */
    public static function termWithinLimitDataProvider(): \Generator
    {
        $asciiTerm = str_repeat('a', 1024);
        yield 'term of 1024 ASCII characters is stored untruncated' => [
            'term' => $asciiTerm,
        ];

        $exactByteLimitTerm = self::multibyteTermOfExactByteLength(1024);
        yield 'multibyte term of exactly 1024 UTF-8 bytes is stored untruncated' => [
            'term' => $exactByteLimitTerm,
        ];
    }

    #[Test]
    #[DataProvider('termWithinLimitDataProvider')]
    public function termWithinByteLimitIsStored(string $term): void
    {
        self::assertLessThanOrEqual(1024, strlen($term));

        $dataHandler = $this->saveGlossaryEntryTerm('NEW1', $term);

        self::assertSame([], $dataHandler->errorLog);
        $uid = (int)($dataHandler->substNEWwithIDs['NEW1'] ?? 0);
        self::assertGreaterThan(0, $uid);
        self::assertSame($term, $this->fetchStoredTerm($uid));
    }

    /**
     * A term of 1024 multibyte characters is far above 1024 UTF-8 bytes, since every character
     * of {@see self::MULTIBYTE_PATTERN} uses two to four bytes. TYPO3's character based
     * :php:`max` alone cannot catch this - only the byte based check does. The whole record is
     * rejected, not just the term: silently inserting a glossary entry with an empty term would
     * create an unwanted row and bypass the field's `required` setting.
     */
    #[Test]
    public function newRecordWithTermExceedingByteLimitIsNotCreated(): void
    {
        $oversizedTerm = $this->oversizedMultibyteTerm();

        $dataHandler = $this->saveGlossaryEntryTerm('NEW1', $oversizedTerm);

        self::assertNotSame([], $dataHandler->errorLog);
        self::assertStringContainsString('1024', $dataHandler->errorLog[0]);
        self::assertArrayNotHasKey('NEW1', $dataHandler->substNEWwithIDs);
        self::assertSame(0, $this->countGlossaryEntriesWithTerm($oversizedTerm));
    }

    /**
     * An existing record must not lose its previously stored, valid term just because an editor
     * submits an oversized one, and other fields submitted together with it must still be saved.
     */
    #[Test]
    public function existingRecordKeepsStoredTermWhenUpdateExceedsByteLimit(): void
    {
        $oversizedTerm = $this->oversizedMultibyteTerm();

        $dataHandler = $this->saveGlossaryEntryTerm(10, $oversizedTerm, ['hidden' => 1]);

        self::assertNotSame([], $dataHandler->errorLog);
        self::assertStringContainsString('1024', $dataHandler->errorLog[0]);
        self::assertSame('hallo', $this->fetchStoredTerm(10));
        self::assertSame(1, $this->fetchStoredHidden(10));
    }

    /**
     * A translated glossary entry (`l10n_parent` pointing to the original) is a record in the
     * same table with the same `term` field, so it must be rejected the same way an update of
     * the original language record is.
     */
    #[Test]
    public function translatedRecordKeepsStoredTermWhenUpdateExceedsByteLimit(): void
    {
        $oversizedTerm = $this->oversizedMultibyteTerm();

        $dataHandler = $this->saveGlossaryEntryTerm(11, $oversizedTerm, ['hidden' => 1]);

        self::assertNotSame([], $dataHandler->errorLog);
        self::assertSame('hello', $this->fetchStoredTerm(11));
        self::assertSame(1, $this->fetchStoredHidden(11));
    }

    private function oversizedMultibyteTerm(): string
    {
        $oversizedTerm = mb_substr(str_repeat(self::MULTIBYTE_PATTERN, 150), 0, 1024);
        self::assertGreaterThan(1024, strlen($oversizedTerm));

        return $oversizedTerm;
    }

    /**
     * @param array<string, mixed> $additionalFields
     */
    private function saveGlossaryEntryTerm(int|string $id, string $term, array $additionalFields = []): DataHandler
    {
        $dataMap = [
            'tx_deepltranslate_glossaryentry' => [
                $id => [
                    'pid' => 2,
                    'term' => $term,
                    ...$additionalFields,
                ],
            ],
        ];
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($dataMap, []);
        $dataHandler->process_datamap();

        return $dataHandler;
    }

    private function fetchStoredTerm(int $uid): string
    {
        return (string)$this->fetchStoredField($uid, 'term');
    }

    private function fetchStoredHidden(int $uid): int
    {
        return (int)$this->fetchStoredField($uid, 'hidden');
    }

    private function fetchStoredField(int $uid, string $field): mixed
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('tx_deepltranslate_glossaryentry');
        $queryBuilder->getRestrictions()->removeAll();

        return $queryBuilder
            ->select($field)
            ->from('tx_deepltranslate_glossaryentry')
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchOne();
    }

    private function countGlossaryEntriesWithTerm(string $term): int
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('tx_deepltranslate_glossaryentry');
        $queryBuilder->getRestrictions()->removeAll();

        return (int)$queryBuilder
            ->count('uid')
            ->from('tx_deepltranslate_glossaryentry')
            ->where(
                $queryBuilder->expr()->eq(
                    'term',
                    $queryBuilder->createNamedParameter($term)
                )
            )
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * Builds a term of exactly the given number of UTF-8 bytes from {@see self::MULTIBYTE_PATTERN},
     * appending pattern characters until the next one would exceed the target, then padding the
     * remainder with single-byte ASCII characters to hit the byte count exactly.
     */
    private static function multibyteTermOfExactByteLength(int $byteLength): string
    {
        $term = '';
        $usedBytes = 0;
        $patternChars = mb_str_split(self::MULTIBYTE_PATTERN);
        $index = 0;
        while (true) {
            $char = $patternChars[$index % count($patternChars)];
            $charBytes = strlen($char);
            if ($usedBytes + $charBytes > $byteLength) {
                break;
            }
            $term .= $char;
            $usedBytes += $charBytes;
            $index++;
        }
        $term .= str_repeat('a', $byteLength - $usedBytes);

        return $term;
    }
}
