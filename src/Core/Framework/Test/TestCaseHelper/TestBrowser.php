<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseHelper;

use Shopware\Core\Framework\Routing\RequestTransformerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\BrowserKit\CookieJar;
use Symfony\Component\BrowserKit\History;
use Symfony\Component\BrowserKit\Request as DomRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;

class TestBrowser extends KernelBrowser
{
    /**
     * @var RequestTransformerInterface
     */
    private $requestTransformer;

    public function __construct(KernelInterface $kernel, array $server = [], ?History $history = null, ?CookieJar $cookieJar = null)
    {
        parent::__construct($kernel, $server, $history, $cookieJar);

        /** @var RequestTransformerInterface $transformer */
        $transformer = $this->getContainer()->get(RequestTransformerInterface::class);
        $this->requestTransformer = $transformer;
    }

    protected function filterRequest(DomRequest $request): Request
    {
        $filteredRequest = parent::filterRequest($request);

        return $this->requestTransformer->transform($filteredRequest);
    }
}
