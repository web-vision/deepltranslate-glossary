<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Client\Fixtures;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use WebVision\Deepltranslate\Core\Client\DeepLClientFactoryInterface;
use WebVision\Deepltranslate\Core\Client\DeepLClientInterface;
use WebVision\Deepltranslate\Core\ClientInterface as DeepltranslateCoreClientInterface;
use WebVision\Deepltranslate\Core\ConfigurationInterface;

/**
 * Creates clients whose first request is answered with "404 Not Found", as a server URL
 * pointing at something else than DeepL does. DeepL is not asked again on such an answer.
 */
final class NotFoundAnsweringClientFactory implements DeepLClientFactoryInterface
{
    public function __construct(
        private readonly DeepLClientFactoryInterface $clientFactory,
    ) {
    }

    public function create(
        DeepltranslateCoreClientInterface $context,
        ?ConfigurationInterface $configuration = null,
        ?GuzzleClientInterface $client = null,
        ?array $options = null,
    ): DeepLClientInterface {
        $handler = new MockHandler([
            new Response(404, ['Content-Type' => 'application/json'], '{"message":"Not found"}'),
        ]);

        return $this->clientFactory->create(
            $context,
            $configuration,
            new Client(['handler' => HandlerStack::create($handler)]),
            $options
        );
    }

    public function buildDeepLClientOptions(
        DeepltranslateCoreClientInterface $context,
        ConfigurationInterface $configuration,
        GuzzleClientInterface $client,
        ?array $options = null,
    ): array {
        return $this->clientFactory->buildDeepLClientOptions($context, $configuration, $client, $options);
    }
}
