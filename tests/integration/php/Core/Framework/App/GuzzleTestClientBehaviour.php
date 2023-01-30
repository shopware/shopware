<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

trait GuzzleTestClientBehaviour
{
    use IntegrationTestBehaviour;

    /**
     * @before
     *
     * @after
     */
    public function resetHistory(): void
    {
        /** @var GuzzleHistoryCollector $historyCollector */
        $historyCollector = $this->getContainer()->get(GuzzleHistoryCollector::class);
        $historyCollector->resetHistory();
        /** @var MockHandler $mockHandler */
        $mockHandler = $this->getContainer()->get(MockHandler::class);
        $mockHandler->reset();
        /** @var TestAppServer $testServer */
        $testServer = $this->getContainer()->get(TestAppServer::class);
        $testServer->reset();
    }

    public function getLastRequest(): ?RequestInterface
    {
        /** @var MockHandler $mockHandler */
        $mockHandler = $this->getContainer()->get(MockHandler::class);

        return $mockHandler->getLastRequest();
    }

    public function getPastRequest(int $index): RequestInterface
    {
        /** @var GuzzleHistoryCollector $historyCollector */
        $historyCollector = $this->getContainer()->get(GuzzleHistoryCollector::class);

        return $historyCollector->getHistory()[$index]['request'];
    }

    public function getRequestCount(): int
    {
        /** @var GuzzleHistoryCollector $historyCollector */
        $historyCollector = $this->getContainer()->get(GuzzleHistoryCollector::class);

        return \count($historyCollector->getHistory());
    }

    public function appendNewResponse(ResponseInterface|\Exception|PromiseInterface $response): void
    {
        /** @var MockHandler $mockHandler */
        $mockHandler = $this->getContainer()->get(MockHandler::class);
        $mockHandler->append($response);
    }

    public function didRegisterApp(): bool
    {
        /** @var TestAppServer $testServer */
        $testServer = $this->getContainer()->get(TestAppServer::class);

        return $testServer->didRegister();
    }
}
