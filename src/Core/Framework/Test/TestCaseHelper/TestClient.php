<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseHelper;

use Doctrine\DBAL\Connection;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Component\BrowserKit\CookieJar;
use Symfony\Component\BrowserKit\History;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;

class TestClient extends Client
{
    /**
     * @var RequestTransformer
     */
    private $requestTransformer;

    public function __construct(KernelInterface $kernel, Connection $connection, array $server = [], ?History $history = null, ?CookieJar $cookieJar = null)
    {
        parent::__construct($kernel, $server, $history, $cookieJar);

        $this->requestTransformer = new RequestTransformer($connection);
    }

    protected function filterRequest($request): Request
    {
        $request = parent::filterRequest($request);

        return $this->requestTransformer->transform($request);
    }
}
