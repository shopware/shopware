<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App;

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
     * @after
     */
    public function resetHistory(): void
    {
        $this->getContainer()->get(GuzzleHistoryCollector::class)->resetHistory();
        $this->getContainer()->get(MockHandler::class)->reset();
        $this->getContainer()->get(TestAppServer::class)->reset();
    }

    public function getLastRequest(): RequestInterface
    {
        return $this->getContainer()->get(MockHandler::class)->getLastRequest();
    }

    public function getPastRequest(int $index): RequestInterface
    {
        return $this->getContainer()->get(GuzzleHistoryCollector::class)->getHistory()[$index]['request'];
    }

    public function getRequestCount(): int
    {
        return \count($this->getContainer()->get(GuzzleHistoryCollector::class)->getHistory());
    }

    /**
     * @param $response ResponseInterface|\Exception|PromiseInterface
     */
    public function appendNewResponse($response): void
    {
        $this->getContainer()->get(MockHandler::class)->append($response);
    }

    public function didRegisterApp(): bool
    {
        return $this->getContainer()->get(TestAppServer::class)->didRegister();
    }
}
