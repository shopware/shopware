<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Promise\PromiseInterface;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Test\Integration\App\GuzzleHistoryCollector;
use Shopware\Core\Test\Integration\App\TestAppServer;

trait GuzzleTestClientBehaviour
{
    use IntegrationTestBehaviour;

    #[Before]
    #[After]
    public function resetHistory(): void
    {
        $historyCollector = $this->getContainer()->get(GuzzleHistoryCollector::class);
        static::assertInstanceOf(GuzzleHistoryCollector::class, $historyCollector);
        $historyCollector->resetHistory();
        $mockHandler = $this->getContainer()->get(MockHandler::class);
        static::assertInstanceOf(MockHandler::class, $mockHandler);
        $mockHandler->reset();
        $testServer = $this->getContainer()->get(TestAppServer::class);
        static::assertInstanceOf(TestAppServer::class, $testServer);
        $testServer->reset();
    }

    public function getLastRequest(): ?RequestInterface
    {
        $mockHandler = $this->getContainer()->get(MockHandler::class);
        static::assertInstanceOf(MockHandler::class, $mockHandler);

        return $mockHandler->getLastRequest();
    }

    public function getPastRequest(int $index): RequestInterface
    {
        $historyCollector = $this->getContainer()->get(GuzzleHistoryCollector::class);
        static::assertInstanceOf(GuzzleHistoryCollector::class, $historyCollector);

        return $historyCollector->getHistory()[$index]['request'];
    }

    public function getRequestCount(): int
    {
        $historyCollector = $this->getContainer()->get(GuzzleHistoryCollector::class);
        static::assertInstanceOf(GuzzleHistoryCollector::class, $historyCollector);

        return \count($historyCollector->getHistory());
    }

    public function appendNewResponse(ResponseInterface|\Exception|PromiseInterface $response): void
    {
        $mockHandler = $this->getContainer()->get(MockHandler::class);
        static::assertInstanceOf(MockHandler::class, $mockHandler);
        $mockHandler->append($response);
    }

    public function didRegisterApp(): bool
    {
        $testServer = $this->getContainer()->get(TestAppServer::class);
        static::assertInstanceOf(TestAppServer::class, $testServer);

        return $testServer->didRegister();
    }
}
