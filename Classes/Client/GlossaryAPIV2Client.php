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
        $prepareEntriesForGlossary = [];
        foreach ($entries as $entry) {
            /*
             * as the version without trimming in TCA is already published,
             * we trim a second time here
             * to avoid errors in DeepL client
             */
            $source = trim($entry['source']);
            $target = trim($entry['target']);
            if (empty($source) || empty($target)) {
                continue;
            }
            $prepareEntriesForGlossary[$source] = $target;
        }
        try {
            return $this->client()->createGlossary(
                $glossaryName,
                $sourceLang,
                $targetLang,
                GlossaryEntries::fromEntries($prepareEntriesForGlossary)
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
