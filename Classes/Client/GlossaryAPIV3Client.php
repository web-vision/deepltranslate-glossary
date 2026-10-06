<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Client;

use DeepL\DeepLException;
use DeepL\GlossaryLanguagePair;
use DeepL\GlossaryNotFoundException;
use DeepL\MultilingualGlossaryDictionaryEntries;
use DeepL\MultilingualGlossaryDictionaryInfo;
use DeepL\MultilingualGlossaryInfo;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
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
            $this->logAndRethrow($exception, 'getGlossaryLanguagePairs');
        }
    }

    public function getAllGlossaries(): array
    {
        try {
            return $this->client()->listMultilingualGlossaries();
        } catch (DeepLException $exception) {
            $this->logAndRethrow($exception, 'getAllGlossaries');
        }
    }

    public function getGlossary(string $glossaryId): MultilingualGlossaryInfo
    {
        try {
            return $this->client()->getMultilingualGlossary($glossaryId);
        } catch (DeepLException $exception) {
            $this->logAndRethrow($exception, 'getGlossary', $glossaryId);
        }
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
            $this->logAndRethrow($exception, 'createGlossary');
        }
    }

    /**
     * @param MultilingualGlossaryDictionaryEntries[] $dictionaries
     */
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
            $this->logAndRethrow($exception, 'updateGlossary', $glossaryId);
        }
    }

    public function replaceDictionary(
        string $glossaryId,
        MultilingualGlossaryDictionaryEntries $dictionary,
    ): MultilingualGlossaryDictionaryInfo {
        try {
            return $this->client()->replaceMultilingualGlossaryDictionary(
                $glossaryId,
                $dictionary,
            );
        } catch (DeepLException $exception) {
            $this->logAndRethrow($exception, 'replaceDictionary', $glossaryId);
        }
    }

    public function deleteGlossary(string $glossaryId): void
    {
        try {
            $this->client()->deleteMultilingualGlossary($glossaryId);
        } catch (DeepLException $exception) {
            $this->logAndRethrow($exception, 'deleteGlossary', $glossaryId);
        }
    }

    public function deleteDictionary(
        string $glossaryId,
        string $sourceLanguage,
        string $targetLanguage,
    ): void {
        try {
            $this->client()->deleteMultilingualGlossaryDictionary(
                $glossaryId,
                null,
                $sourceLanguage,
                $targetLanguage,
            );
        } catch (DeepLException $exception) {
            $this->logAndRethrow($exception, 'deleteDictionary', $glossaryId);
        }
    }

    /**
     * @return MultilingualGlossaryDictionaryEntries[]
     */
    public function getGlossaryEntries(
        string $glossaryId,
        string $sourceLanguage,
        string $targetLanguage,
    ): array {
        try {
            return $this->client()->getMultilingualGlossaryEntries(
                $glossaryId,
                $sourceLanguage,
                $targetLanguage,
            );
        } catch (DeepLException $exception) {
            $this->logAndRethrow($exception, 'getGlossaryEntries', $glossaryId);
        }
    }

    /**
     * Logs the failed API call and rethrows the original exception.
     *
     * Returning a placeholder {@see MultilingualGlossaryInfo} instead would make a failed call
     * indistinguishable from a successful one, so a caller would persist an empty glossary id
     * together with a fresh synchronisation timestamp.
     *
     * A glossary removed at DeepL is expected by the callers of a request on an existing
     * glossary, which recover from it, so it is logged as debug information only. A request
     * addressing no glossary, like listing or creating glossaries, is not answered with "not
     * found" by DeepL, so such an answer points at a wrong server URL and is logged as error.
     *
     * @param string $glossaryId the glossary the request addresses, empty for none
     *
     * @throws DeepLException
     */
    private function logAndRethrow(DeepLException $exception, string $operation, string $glossaryId = ''): never
    {
        $this->logger->log(
            $exception instanceof GlossaryNotFoundException && $glossaryId !== '' ? LogLevel::DEBUG : LogLevel::ERROR,
            sprintf(
                'DeepL glossary request %s failed: %s (%d)',
                $operation,
                $exception->getMessage(),
                $exception->getCode()
            ),
            [
                'operation' => $operation,
                'glossaryId' => $glossaryId,
            ]
        );

        throw $exception;
    }
}
