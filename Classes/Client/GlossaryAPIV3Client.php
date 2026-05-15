<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Client;

use DeepL\DeepLException;
use DeepL\GlossaryLanguagePair;
use DeepL\MultilingualGlossaryDictionaryEntries;
use DeepL\MultilingualGlossaryInfo;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use WebVision\Deepltranslate\Core\AbstractClient;
use WebVision\Deepltranslate\Core\Client\DeepLClientFactoryInterface;

/**
 * Client implementation for Glossary API v3, see {@see GlossaryAPIV3ClientInterface}.
 * @internal No public API.
 */
#[AsAlias(id: GlossaryAPIV3ClientInterface::class, public: true)]
final class GlossaryAPIV3Client extends AbstractClient implements GlossaryAPIV3ClientInterface
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

    public function getAllGlossaries(): array
    {
        try {
            return $this->client()->listMultilingualGlossaries();
        } catch (DeepLException $exception) {
            $this->logger->error(sprintf(
                '%s (%d)',
                $exception->getMessage(),
                $exception->getCode()
            ));
        }

        return [];
    }

    public function getGlossary(string $glossaryId): MultilingualGlossaryInfo
    {
        try {
            return $this->client()->getMultilingualGlossary($glossaryId);
        } catch (DeepLException $exception) {
            $this->logger->error(sprintf(
                '%s (%d)',
                $exception->getMessage(),
                $exception->getCode()
            ));
        }

        return new MultilingualGlossaryInfo(
            '',
            'Not defined',
            new \DateTime(),
            []
        );
    }

    /**
     * @param MultilingualGlossaryDictionaryEntries[] $dictionaries
     */
    public function createGlossary(
        string $glossaryName,
        array $dictionaries,
    ): MultilingualGlossaryInfo {
        try {
            return $this->client()->createMultilingualGlossary(
                $glossaryName,
                $dictionaries,
            );
        } catch (DeepLException $exception) {
            $this->logger->error(sprintf(
                '%s (%d)',
                $exception->getMessage(),
                $exception->getCode()
            ));
        }

        return new MultilingualGlossaryInfo(
            '',
            'Not defined',
            new \DateTime(),
            []
        );
    }

    public function updateGlossary(
        string $glossaryId,
        array $dictionaries,
        ?string $name = null,
    ): MultilingualGlossaryInfo {
        try {
            return $this->client()->updateMultilingualGlossary(
                $glossaryId,
                $name,
                $dictionaries,
            );
        } catch (DeepLException $exception) {
            $this->logger->error(sprintf(
                '%s (%d)',
                $exception->getMessage(),
                $exception->getCode()
            ));
        }

        return new MultilingualGlossaryInfo(
            '',
            'Not defined',
            new \DateTime(),
            []
        );
    }
}
