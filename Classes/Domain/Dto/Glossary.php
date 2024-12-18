<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Domain\Dto;

use DateTimeImmutable;
use TYPO3\CMS\Core\Utility\MathUtility;

final class Glossary
{
    private function __construct(
        private readonly int                $uid,
        private readonly string             $glossaryId,
        private readonly string             $name,
        private readonly ?DateTimeImmutable $lastSync,
        private readonly bool               $ready
    ) {
    }

    /**
     * @param array{
     *     uid?: int|string,
     *     glossary_id?: string,
     *     glossary_name?: string,
     *     glossary_lastsync?: int|string,
     *     glossary_ready?: int|string
     * }|array<string, mixed> $result
     * @return self
     */
    public static function fromDatabase(array $result): self
    {
        $lastSync = null;
        $ready = false;
        if (
            array_key_exists('glossary_lastsync', $result)
            && MathUtility::canBeInterpretedAsInteger($result['glossary_lastsync'])
        ) {
            $lastSync = DateTimeImmutable::createFromFormat('U', (string)$result['glossary_lastsync']);
        }

        if (array_key_exists('glossary_ready', $result)) {
            $ready = !(((int)$result['glossary_ready']) === 0);
        }

        return new self(
            (int)$result['uid'],
            $result['glossary_id'],
            $result['glossary_name'],
            $lastSync,
            $ready
        );
    }

    public static function createDummy(): self
    {
        return new self(
            0,
            '',
            'UNDEFINED',
            null,
            false
        );
    }

    public function getUid(): ?int
    {
        return $this->uid;
    }

    public function getGlossaryId(): string
    {
        return $this->glossaryId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLastSync(): ?DateTimeImmutable
    {
        return $this->lastSync;
    }

    public function isReady(): bool
    {
        return $this->ready;
    }
}
