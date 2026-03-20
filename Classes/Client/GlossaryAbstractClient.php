<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Client;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use WebVision\Deepltranslate\Core\AbstractClient;
use WebVision\Deepltranslate\Core\Client\DeepLClientInterface;
use WebVision\Deepltranslate\Core\GlossaryV3Interface;

/**
 * @internal No public API.
 */
#[AsAlias(id: GlossaryV3Interface::class, public: true)]
final class GlossaryAbstractClient extends AbstractClient implements GlossaryV3Interface
{

    public function __construct(
        protected LoggerInterface $logger,
        protected DeepLClientInterface $client,
    ) {
    }
}
