<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Routing;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Framework\Routing\RequestHelper;
use Symfony\Component\HttpFoundation\Request;

class RequestHelperTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @dataProvider urlCases
     */
    public function testUrls(RequestCase $case): void
    {
        $request = Request::create('//' . $case->host);
        $requestHelper = new RequestHelper();

        $requestHelper->setBaseUrlAndPathInfo(
            $request,
            $case->baseUrl,
            $case->pathInfo
        );

        static::assertSame($case->expected, $requestHelper->getSchemeAndHttpHost($request) . $request->getRequestUri());
        static::assertSame($case->baseUrl, $request->getBaseUrl());
        static::assertSame($case->pathInfo, $request->getPathInfo());
    }

    public function urlCases()
    {
        return [
            'test-punycode' => [
                new RequestCase('xn--mller-kva.de', '', '', 'http://müller.de'),
            ],
            'test-empty' => [
                new RequestCase('localhost', '', '', 'http://localhost'),
            ],
            'test-path-info' => [
                new RequestCase('localhost', '', '/test', 'http://localhost/test'),
            ],
            'test-base-url' => [
                new RequestCase('localhost', '/public', '/test', 'http://localhost/public/test'),
            ],
            'test-port' => [
                new RequestCase('localhost:8080', '', '', 'http://localhost:8080'),
            ],
        ];
    }
}

class RequestCase
{
    /**
     * @var string
     */
    public $host;

    /**
     * @var string
     */
    public $baseUrl;

    /**
     * @var string
     */
    public $pathInfo;

    /**
     * @var string
     */
    public $expected;

    public function __construct(string $host, string $baseUrl, string $pathInfo, string $expected)
    {
        $this->host = $host;
        $this->baseUrl = $baseUrl;
        $this->pathInfo = $pathInfo;
        $this->expected = $expected;
    }
}
