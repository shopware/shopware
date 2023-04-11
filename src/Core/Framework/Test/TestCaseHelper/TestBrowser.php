<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseHelper;

use Shopware\Core\Framework\Event\BeforeSendResponseEvent;
use Shopware\Core\Framework\Routing\RequestTransformerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\BrowserKit\CookieJar;
use Symfony\Component\BrowserKit\History;
use Symfony\Component\BrowserKit\Request as DomRequest;
use Symfony\Component\BrowserKit\Response as DomResponse;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 */
class TestBrowser extends KernelBrowser
{
    /**
     * @var Request
     */
    protected $lastRequest;

    private readonly RequestTransformerInterface $requestTransformer;

    /**
     * @param array<string, mixed> $server
     */
    public function __construct(
        KernelInterface $kernel,
        private readonly EventDispatcherInterface $eventDispatcher,
        array $server = [],
        ?History $history = null,
        ?CookieJar $cookieJar = null
    ) {
        parent::__construct($kernel, $server, $history, $cookieJar);

        $transformer = $this->getContainer()->get(RequestTransformerInterface::class);
        $this->requestTransformer = $transformer;
    }

    public function setServerParameter(string $key, mixed $value): void
    {
        $this->server[$key] = $value;
    }

    protected function filterRequest(DomRequest $request): Request
    {
        $filteredRequest = parent::filterRequest($request);
        $transformedRequest = $this->requestTransformer->transform($filteredRequest);

        return $this->lastRequest = $transformedRequest;
    }

    /**
     * @param Response $response
     */
    protected function filterResponse(object $response): DomResponse
    {
        $event = new BeforeSendResponseEvent($this->lastRequest, $response);
        $this->eventDispatcher->dispatch($event);

        return parent::filterResponse($response);
    }
}
