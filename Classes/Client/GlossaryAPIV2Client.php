<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Client;

use DeepL\DeepLException;
use DeepL\GlossaryEntries;
use DeepL\GlossaryInfo;
use DeepL\GlossaryLanguagePair;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use WebVision\Deepltranslate\Core\AbstractClient;
use WebVision\Deepltranslate\Core\Client\DeepLClientFactoryInterface;

/**
 * Client implementation for Glossary API v2, see {@see GlossaryAPIV2ClientInterface}.
 * @internal No public API.
 */
#[AsAlias(id: GlossaryAPIV2ClientInterface::class, public: true)]
final class GlossaryAPIV2Client extends AbstractClient implements GlossaryAPIV2ClientInterface
{
    /**
     * @internal
     * @todo typo3/cms-core:>=13.4.29 Replace constructor with `inject*()` methods in {@see AbstractClient},
     *       link: https://review.typo3.org/c/Packages/TYPO3.CMS/+/89244
     */
    public function __construct(
        protected LoggerInterface $logger,
        protected DeepLClientFactoryInterface $clientFactory,
    ) {
    }

    /**
     * @return GlossaryLanguagePair[]
     */
    public function getGlossaryLanguagePairs(): array
    {
        try {
            return $this->client()->getGlossaryLanguages();
        } catch (DeepLException $exception) {
            $this->logger->error(sprintf(
                '%s (%d)',
                $exception->getMessage(),
                $exception->getCode()
            ));
        }

        return [];
    }

    /**
     * @return GlossaryInfo[]
     */
    public function getAllGlossaries(): array
    {
        try {
            return $this->client()->listGlossaries();
        } catch (DeepLException $exception) {
            $this->logger->error(sprintf(
                '%s (%d)',
                $exception->getMessage(),
                $exception->getCode()
            ));
        }

        return [];
    }

    /**
     * DeepL Glossary API v2
     *
     * @depreacted will be removed as soon as DeepL API drops support for v2
     */
    public function getGlossary(string $glossaryId): ?GlossaryInfo
    {
        try {
            return $this->client()->getGlossary($glossaryId);
        } catch (DeepLException $exception) {
            $this->logger->error(sprintf(
                '%s (%d)',
                $exception->getMessage(),
                $exception->getCode()
            ));
        }

        return null;
    }

    /**
     * DeepL Glossary API v2
     *
     * @depreacted will be removed as soon as DeepL API drops support for v2
     * @param array<int, array{source: string, target: string}> $entries
     */
    public function createGlossary(
        string $glossaryName,
        string $sourceLang,
        string $targetLang,
        array $entries
    ): GlossaryInfo {
        try {
            return $this->client()->createGlossary(
                $glossaryName,
                $sourceLang,
                $targetLang,
                GlossaryEntries::fromEntries($this->sanitizeEntries($entries))
            );
        } catch (DeepLException $e) {
            return new GlossaryInfo(
                '',
                '',
                false,
                '',
                '',
                new \DateTime(),
                0
            );
        }
    }

    /**
     * Trims both sides of a term pair and drops pairs which are unusable afterwards.
     *
     * DeepL rejects a term without non-whitespace characters, and a term whose source or
     * target text exceeds 1024 UTF-8 bytes, see
     * https://developers.deepl.com/api-reference/multilingual-glossaries. Either failure
     * answers the whole createGlossary request with an error, so a single unusable pair
     * would abort the synchronization of an entire glossary folder. Dropping such pairs
     * here keeps the remaining, valid pairs in sync.
     *
     * @param array<int, array{source: string, target: string}> $entries
     * @return array<string, string>
     */
    private function sanitizeEntries(array $entries): array
    {
        $sanitizedEntries = [];
        foreach ($entries as $entry) {
            $source = trim($entry['source']);
            $target = trim($entry['target']);
            if ($source === '' || $target === '') {
                continue;
            }
            if (strlen($source) > 1024 || strlen($target) > 1024) {
                $this->logger->warning(sprintf(
                    'Glossary term pair "%s" => "%s" exceeds the DeepL limit of 1024 UTF-8 bytes and was skipped.',
                    $source,
                    $target
                ));
                continue;
            }
            $sanitizedEntries[$source] = $target;
        }

        return $sanitizedEntries;
    }

    /**
     * DeepL Glossary API v2
     *
     * @depreacted will be removed as soon as DeepL API drops support for v2
     */
    public function deleteGlossary(string $glossaryId): void
    {
        try {
            $this->client()->deleteGlossary($glossaryId);
        } catch (DeepLException $exception) {
            $this->logger->error(sprintf(
                '%s (%d)',
                $exception->getMessage(),
                $exception->getCode()
            ));
        }
    }

    /**
     * DeepL Glossary API v2
     *
     * @depreacted will be removed as soon as DeepL API drops support for v2
     */
    public function getGlossaryEntries(string $glossaryId): ?GlossaryEntries
    {
        try {
            return $this->client()->getGlossaryEntries($glossaryId);
        } catch (DeepLException $exception) {
            $this->logger->error(sprintf(
                '%s (%d)',
                $exception->getMessage(),
                $exception->getCode()
            ));
        }

        return null;
    }
}
