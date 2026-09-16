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
    /**
     * Number of platform snippet files downloaded per locale (Administration, Core, Storefront).
     */
    private const PLATFORM_FILE_COUNT = 3;

    /**
     * Number of snippet files downloaded per configured plugin per locale (Administration, Storefront).
     */
    private const PLUGIN_FILE_COUNT = 2;

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

        $fileCount = self::PLATFORM_FILE_COUNT + \count($config->plugins) * self::PLUGIN_FILE_COUNT;

        for ($i = 0; $i < $fileCount; ++$i) {
            $this->appendTranslationResponse(new Response(200, [], '{}'));
        }
    }

    #[Before]
    public function resetTranslationMock(): void
    {
        $this->getTranslationRequestHandler()->reset();
    }

    #[After]
    public function assertTranslationMockConsumed(): void
    {
        $handler = $this->getTranslationRequestHandler();
        $remaining = $handler->count();
        $handler->reset();

        // an unconsumed queue means requests did not reach the mocked client — this guards
        // against the override being lost again (see #18067) and against over-queueing
        if ($this->status()->isSuccess()) {
            static::assertSame(0, $remaining, \sprintf('%d queued translation mock responses were not consumed', $remaining));
        }
    }
}
