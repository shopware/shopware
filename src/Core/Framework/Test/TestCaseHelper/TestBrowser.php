<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseHelper;

use Shopware\Core\Framework\Event\BeforeSendResponseEvent;
use Shopware\Core\Framework\Routing\RequestTransformerInterface;
use Shopware\Core\SalesChannelRequest;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\BrowserKit\CookieJar;
use Symfony\Component\BrowserKit\History;
use Symfony\Component\BrowserKit\Request as DomRequest;
use Symfony\Component\BrowserKit\Response as DomResponse;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class TestBrowser extends KernelBrowser
{
    /**
     * @var Request
     */
    protected $lastRequest;

    protected bool $csrfDisabled = false;

    private RequestTransformerInterface $requestTransformer;

    private EventDispatcherInterface $eventDispatcher;

    public function __construct($kernel, EventDispatcherInterface $eventDispatcher, array $server = [], ?History $history = null, ?CookieJar $cookieJar = null)
    {
        parent::__construct($kernel, $server, $history, $cookieJar);

        $transformer = $this->getContainer()->get(RequestTransformerInterface::class);
        $this->requestTransformer = $transformer;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function disableCsrf(): void
    {
        $this->csrfDisabled = true;
    }

    public function enableCsrf(): void
    {
        $this->csrfDisabled = false;
    }

    /**
     * @param string
     * @param string|object|array $value
     */
    public function setServerParameter($key, $value): void
    {
        $this->server[$key] = $value;
    }

    protected function filterRequest(DomRequest $request): Request
    {
        $filteredRequest = parent::filterRequest($request);
        $transformedRequest = $this->requestTransformer->transform($filteredRequest);
        if ($this->csrfDisabled) {
            $transformedRequest->attributes->set(SalesChannelRequest::ATTRIBUTE_CSRF_PROTECTED, false);
        }

        return $this->lastRequest = $transformedRequest;
    }

    /**
     * @param Response $response
     */
    protected function filterResponse($response): DomResponse
    {
        $event = new BeforeSendResponseEvent($this->lastRequest, $response);
        $this->eventDispatcher->dispatch($event);

        return parent::filterResponse($response);
    }
}
