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

class TestBrowser extends KernelBrowser
{
    /**
     * @var Request
     */
    protected $lastRequest;

    /**
     * @var bool
     */
    protected $csrfDisabled = false;

    /**
     * @var RequestTransformerInterface
     */
    private $requestTransformer;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct($kernel, array $server = [], ?History $history = null, ?CookieJar $cookieJar = null, EventDispatcherInterface $eventDispatcher)
    {
        parent::__construct($kernel, $server, $history, $cookieJar);

        /** @var RequestTransformerInterface $transformer */
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
     *
     * @return DomResponse
     */
    protected function filterResponse($response)
    {
        $event = new BeforeSendResponseEvent($this->lastRequest, $response);
        $this->eventDispatcher->dispatch($event);

        $filteredResponse = parent::filterResponse($response);

        return $filteredResponse;
    }
}
