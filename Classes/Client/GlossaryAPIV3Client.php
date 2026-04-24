<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Client;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use WebVision\Deepltranslate\Core\AbstractClient;
use WebVision\Deepltranslate\Core\Client\DeepLClientFactoryInterface;

/**
 * Client implementation for Glossary API v2, see {@see GlossaryAPIV2ClientInterface}.
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
}
