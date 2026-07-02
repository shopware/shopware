<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\Snippet;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use Psr\Http\Message\ResponseInterface;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;

/**
 * Controls the mocked translation HTTP client (`shopware.translation.client`) in integration tests,
 * so no real requests are made to the translations repository.
 *
 * @internal
 */
trait TranslationClientBehaviour
{
    public function getTranslationRequestHandler(): MockHandler
    {
        $handler = static::getContainer()->get('shopware.translation.mock_handler');
        static::assertInstanceOf(MockHandler::class, $handler);

        return $handler;
    }

    public function appendTranslationResponse(ResponseInterface $response): void
    {
        $this->getTranslationRequestHandler()->append($response);
    }

    /**
     * Queues one successful empty response per snippet file a single `TranslationLoader::load()` call
     * downloads: the three platform bundles plus two bundles per configured plugin.
     */
    public function appendTranslationFileResponses(): void
    {
        $config = static::getContainer()->get(TranslationConfig::class);
        static::assertInstanceOf(TranslationConfig::class, $config);

        $fileCount = 3 + \count($config->plugins) * 2;

        for ($i = 0; $i < $fileCount; ++$i) {
            $this->appendTranslationResponse(new Response(200, [], '{}'));
        }
    }

    #[Before]
    #[After]
    public function resetTranslationMock(): void
    {
        $this->getTranslationRequestHandler()->reset();
    }
}
