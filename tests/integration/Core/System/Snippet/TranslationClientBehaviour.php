<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\Snippet;

use GuzzleHttp\Handler\MockHandler;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use Psr\Http\Message\ResponseInterface;

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

    #[Before]
    #[After]
    public function resetTranslationMock(): void
    {
        $this->getTranslationRequestHandler()->reset();
    }
}
