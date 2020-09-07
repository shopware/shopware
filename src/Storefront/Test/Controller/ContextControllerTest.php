<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Routing\RequestContext;

class ContextControllerTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour;

    /**
     * @var \Symfony\Bundle\FrameworkBundle\KernelBrowser
     */
    private $browser;

    /**
     * @var string
     */
    private $testBaseUrl;

    /**
     * @var string
     */
    private $defaultBaseUrl;

    /**
     * @var string
     */
    private $languageId;

    /**
     * @var RequestContext
     */
    private $lastRouteContext;

    /**
     * @var object|\Symfony\Bundle\FrameworkBundle\Routing\Router|null
     */
    private $router;

    protected function setUp(): void
    {
        $this->router = $this->getContainer()->get('router');

        /** @var RequestContext $context */
        $context = $this->router->getContext();

        $this->lastRouteContext = clone $context;

        $this->languageId = Uuid::randomHex();
        $localeId = Uuid::randomHex();

        $this->defaultBaseUrl = $_SERVER['APP_URL'];
        $this->testBaseUrl = $_SERVER['APP_URL'] . '/tst-TST';

        $this->getContainer()->get(Connection::class)->executeUpdate('DELETE FROM sales_channel');

        $domains = [
            [
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => $this->defaultBaseUrl,
            ],
            [
                'language' => [
                    'id' => $this->languageId,
                    'name' => 'Test',
                    'locale' => [
                        'id' => $localeId,
                        'name' => 'Test',
                        'code' => 'x-tst-TST',
                        'territory' => 'test',
                    ],
                    'translationCodeId' => $localeId,
                ],
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => $this->testBaseUrl,
            ],
        ];

        $this->browser = $this->createCustomSalesChannelBrowser([
            'domains' => $domains,
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM], ['id' => $this->languageId]],
        ]);

        // HACK to deactivate csrf protection. The check is only done once per request
        $this->browser->request(
            'POST',
            $this->defaultBaseUrl . '/checkout/language'
        );
    }

    protected function tearDown(): void
    {
        $this->router->getContext()->setBaseUrl('');
    }

    public function testSwitchToUpperCasePath(): void
    {
        $this->browser->request('GET', $this->defaultBaseUrl);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $this->browser->request(
            'POST',
            $this->defaultBaseUrl . '/checkout/language',
            ['languageId' => $this->languageId],
            []
        );

        $response = $this->browser->getResponse();
        static::assertSame(302, $response->getStatusCode(), $response->getContent());
        static::assertSame($this->testBaseUrl . '/', $response->headers->get('Location'));
    }

    public function testSwitchFromUpperCasePath(): void
    {
        $this->browser->request('GET', $this->testBaseUrl);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $this->browser->request(
            'POST',
            $this->testBaseUrl . '/checkout/language',
            ['languageId' => Defaults::LANGUAGE_SYSTEM],
            []
        );

        $response = $this->browser->getResponse();
        static::assertSame(302, $response->getStatusCode(), $response->getContent());
        static::assertSame($this->defaultBaseUrl . '/', $response->headers->get('Location'));
    }
}
