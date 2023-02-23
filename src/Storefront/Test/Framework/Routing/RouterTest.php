<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Routing;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;

/**
 * @internal
 */
class RouterTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @dataProvider urlCases
     */
    public function testUrls(UrlCase $case): void
    {
        $request = new Request();
        $request->attributes->set(RequestTransformer::SALES_CHANNEL_BASE_URL, $case->baseUrl);

        $stack = $this->getContainer()->get('request_stack');

        // remove all request from stack
        while ($stack->pop()) {
        }

        $stack->push($request);

        $router = $this->getContainer()->get('router');
        $context = $router->getContext();
        $router->setContext(new RequestContext('', 'GET', $case->host));

        $url = $router->generate($case->route, $case->params, $case->type);

        static::assertSame($case->expected, $url);

        while ($stack->pop()) {
        }

        $router->setContext($context);
    }

    public static function urlCases()
    {
        $id = Uuid::randomHex();

        return [
            'test-home-page-without-suffix' => [
                new UrlCase(UrlGeneratorInterface::ABSOLUTE_PATH, '/', '', 'frontend.home.page'),
            ],
            'test-home-page-with-de' => [
                new UrlCase(UrlGeneratorInterface::ABSOLUTE_PATH, '/de/', '/de', 'frontend.home.page'),
            ],
            'test-home-page-with-de-and-slash' => [
                new UrlCase(UrlGeneratorInterface::ABSOLUTE_PATH, '/de/', '/de/', 'frontend.home.page'),
            ],
            'test-home-page-with-de-without-slash' => [
                new UrlCase(UrlGeneratorInterface::ABSOLUTE_PATH, '/de/', 'de', 'frontend.home.page'),
            ],
            'test-home-page-with-null' => [
                new UrlCase(UrlGeneratorInterface::ABSOLUTE_PATH, '/', null, 'frontend.home.page'),
            ],
            'test-navigation-page-with-de' => [
                new UrlCase(UrlGeneratorInterface::ABSOLUTE_PATH, "/de/navigation/{$id}", '/de', 'frontend.navigation.page', ['navigationId' => $id]),
            ],
            'test-home-page-without-suffix-relative' => [
                new UrlCase(UrlGeneratorInterface::RELATIVE_PATH, '', '', 'frontend.home.page'),
            ],
            'test-home-page-with-de-relative' => [
                new UrlCase(UrlGeneratorInterface::RELATIVE_PATH, 'de/', '/de', 'frontend.home.page'),
            ],
            'test-home-page-with-de-and-slash-relative' => [
                new UrlCase(UrlGeneratorInterface::RELATIVE_PATH, 'de/', '/de/', 'frontend.home.page'),
            ],
            'test-home-page-with-de-without-slash-relative' => [
                new UrlCase(UrlGeneratorInterface::RELATIVE_PATH, 'de/', 'de', 'frontend.home.page'),
            ],
            'test-home-page-with-null-relative' => [
                new UrlCase(UrlGeneratorInterface::RELATIVE_PATH, '', null, 'frontend.home.page'),
            ],
            'test-navigation-page-with-de-relative' => [
                new UrlCase(UrlGeneratorInterface::RELATIVE_PATH, "de/navigation/{$id}", '/de', 'frontend.navigation.page', ['navigationId' => $id]),
            ],
            'test-home-page-without-suffix-absolute-url' => [
                new UrlCase(UrlGeneratorInterface::ABSOLUTE_URL, 'http://test.de/', '', 'frontend.home.page'),
            ],
            'test-home-page-with-de-absolute-url' => [
                new UrlCase(UrlGeneratorInterface::ABSOLUTE_URL, 'http://test.de/de/', '/de', 'frontend.home.page'),
            ],
            'test-home-page-with-de-and-slash-absolute-url' => [
                new UrlCase(UrlGeneratorInterface::ABSOLUTE_URL, 'http://test.de/de/', '/de/', 'frontend.home.page'),
            ],
            'test-home-page-with-de-without-slash-absolute-url' => [
                new UrlCase(UrlGeneratorInterface::ABSOLUTE_URL, 'http://test.de/de/', 'de', 'frontend.home.page'),
            ],
            'test-home-page-with-null-absolute-url' => [
                new UrlCase(UrlGeneratorInterface::ABSOLUTE_URL, 'http://test.de/', null, 'frontend.home.page'),
            ],
            'test-navigation-page-with-de-absolute-url' => [
                new UrlCase(UrlGeneratorInterface::ABSOLUTE_URL, "http://test.de/de/navigation/{$id}", '/de', 'frontend.navigation.page', ['navigationId' => $id]),
            ],
            'test-home-page-without-suffix-network-path' => [
                new UrlCase(UrlGeneratorInterface::NETWORK_PATH, '//test.de/', '', 'frontend.home.page'),
            ],
            'test-home-page-with-de-network-path' => [
                new UrlCase(UrlGeneratorInterface::NETWORK_PATH, '//test.de/de/', '/de', 'frontend.home.page'),
            ],
            'test-home-page-with-de-and-slash-network-path' => [
                new UrlCase(UrlGeneratorInterface::NETWORK_PATH, '//test.de/de/', '/de/', 'frontend.home.page'),
            ],
            'test-home-page-with-de-without-slash-network-path' => [
                new UrlCase(UrlGeneratorInterface::NETWORK_PATH, '//test.de/de/', 'de', 'frontend.home.page'),
            ],
            'test-home-page-with-null-network-path' => [
                new UrlCase(UrlGeneratorInterface::NETWORK_PATH, '//test.de/', null, 'frontend.home.page'),
            ],
            'test-navigation-page-with-de-network-path' => [
                new UrlCase(UrlGeneratorInterface::NETWORK_PATH, "//test.de/de/navigation/{$id}", '/de', 'frontend.navigation.page', ['navigationId' => $id]),
            ],

            'test-home-page-without-suffix-absolute-url-with-port' => [
                new UrlCase(UrlGeneratorInterface::ABSOLUTE_URL, 'http://test.de:8000/', '', 'frontend.home.page', [], 'test.de:8000'),
            ],
            'test-home-page-with-de-absolute-url-with-port' => [
                new UrlCase(UrlGeneratorInterface::ABSOLUTE_URL, 'http://test.de:8000/de/', '/de', 'frontend.home.page', [], 'test.de:8000'),
            ],
            'test-home-page-with-de-and-slash-absolute-url-with-port' => [
                new UrlCase(UrlGeneratorInterface::ABSOLUTE_URL, 'http://test.de:8000/de/', '/de/', 'frontend.home.page', [], 'test.de:8000'),
            ],
            'test-home-page-with-de-without-slash-absolute-url-with-port' => [
                new UrlCase(UrlGeneratorInterface::ABSOLUTE_URL, 'http://test.de:8000/de/', 'de', 'frontend.home.page', [], 'test.de:8000'),
            ],
            'test-home-page-with-null-absolute-url-with-port' => [
                new UrlCase(UrlGeneratorInterface::ABSOLUTE_URL, 'http://test.de:8000/', null, 'frontend.home.page', [], 'test.de:8000'),
            ],
            'test-navigation-page-with-de-absolute-url-with-port' => [
                new UrlCase(UrlGeneratorInterface::ABSOLUTE_URL, "http://test.de:8000/de/navigation/{$id}", '/de', 'frontend.navigation.page', ['navigationId' => $id], 'test.de:8000'),
            ],
            'test-home-page-without-suffix-network-path-with-port' => [
                new UrlCase(UrlGeneratorInterface::NETWORK_PATH, '//test.de:8000/', '', 'frontend.home.page', [], 'test.de:8000'),
            ],
            'test-home-page-with-de-network-path-with-port' => [
                new UrlCase(UrlGeneratorInterface::NETWORK_PATH, '//test.de:8000/de/', '/de', 'frontend.home.page', [], 'test.de:8000'),
            ],
            'test-home-page-with-de-and-slash-network-path-with-port' => [
                new UrlCase(UrlGeneratorInterface::NETWORK_PATH, '//test.de:8000/de/', '/de/', 'frontend.home.page', [], 'test.de:8000'),
            ],
            'test-home-page-with-de-without-slash-network-path-with-port' => [
                new UrlCase(UrlGeneratorInterface::NETWORK_PATH, '//test.de:8000/de/', 'de', 'frontend.home.page', [], 'test.de:8000'),
            ],
            'test-home-page-with-null-network-path-with-port' => [
                new UrlCase(UrlGeneratorInterface::NETWORK_PATH, '//test.de:8000/', null, 'frontend.home.page', [], 'test.de:8000'),
            ],
            'test-navigation-page-with-de-network-path-with-port' => [
                new UrlCase(UrlGeneratorInterface::NETWORK_PATH, "//test.de:8000/de/navigation/{$id}", '/de', 'frontend.navigation.page', ['navigationId' => $id], 'test.de:8000'),
            ],
        ];
    }
}

/**
 * @internal
 */
class UrlCase
{
    /**
     * @var string
     */
    public $host;

    /**
     * @var string
     */
    public $route;

    /**
     * @var array
     */
    public $params = [];

    /**
     * @var string
     */
    public $expected;

    /**
     * @see \Symfony\Component\Routing\Generator\UrlGeneratorInterface
     *
     * @var int
     */
    public $type;

    /**
     * @var string|null
     */
    public $baseUrl;

    public function __construct(
        int $type,
        string $expected,
        ?string $baseUrl,
        string $route,
        array $params = [],
        string $host = 'test.de'
    ) {
        $this->route = $route;
        $this->params = $params;
        $this->expected = $expected;
        $this->type = $type;
        $this->baseUrl = $baseUrl;
        $this->host = $host;
    }
}
