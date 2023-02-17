<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Script;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Controller\Exception\PermissionDeniedException;
use Shopware\Core\Framework\Api\Controller\ScriptController;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\ApiRequestContextResolver;
use Shopware\Core\Framework\Script\Execution\Script;
use Shopware\Core\Framework\Script\Execution\ScriptLoader;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\PlatformRequest;
use Shopware\Tests\Integration\Core\Framework\App\AppSystemTestBehaviour;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class ScriptTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AppSystemTestBehaviour;

    private ScriptController $scripController;

    public function setUp(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures');

        $this->scripController = $this->getContainer()->get(ScriptController::class);
    }

    public function testTranslation(): void
    {
        $deLanguageId = $this->getDeDeLanguageId();
        $idsCollection = new IdsCollection();
        $builder = new ProductBuilder($idsCollection, 'product1');
        $builder->price(10);
        $builder->translation(Defaults::LANGUAGE_SYSTEM, 'name', 'English');
        $builder->translation($deLanguageId, 'name', 'Deutsch');
        $builder->write($this->getContainer());


        $request = new Request([
            'productId' => $idsCollection->get('product1'),
        ], [], ['_routeScope' => ['administration']]);

        $response = $this->scripController->execute('translation', $request, Context::createDefaultContext());
        static::assertEquals('English', $response->getContent());

        $request->headers->set(PlatformRequest::HEADER_LANGUAGE_ID, $deLanguageId);
        $this->getContainer()->get(ApiRequestContextResolver::class)->resolve($request);
        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);

        $response = $this->scripController->execute('translation', $request, $context);
        static::assertEquals('Deutsch', $response->getContent());
    }

    public function testRaw(): void
    {
        $response = $this->scripController->execute('raw', new Request(['input' => '<pre']), Context::createDefaultContext());

        static::assertEquals('<xml>&lt;pre</xml>', $response->getContent());
        static::assertEquals(404, $response->getStatusCode());
        static::assertEquals('text/xml', $response->headers->get('Content-Type'));
    }

    public function testRouterGenerate(): void
    {
        $request = new Request();
        $request->attributes->set('_route', ScriptController::ROUTE);
        $requestStack = $this->getContainer()->get(RequestStack::class);
        $requestStack->push($request);

        $controller = $this->getContainer()->get(ScriptController::class);

        $response = $this->scripController->execute('router', $request, Context::createDefaultContext());
        $response = json_decode($response->getContent(), true);

        static::assertEquals('/api/app/router?query=value', $response['listUrl']);
    }

    public function testRouterRedirect(): void
    {
        $request = new Request();
        $request->attributes->set('_route', ScriptController::ROUTE);
        $requestStack = $this->getContainer()->get(RequestStack::class);
        $requestStack->push($request);

        $response = $this->scripController->execute('router-redirect', new Request(), Context::createDefaultContext());

        static::assertEquals('/api/app/router?query=value', $response->headers->get('Location'));
    }

    public function testSecurity(): void
    {
        $response = $this->scripController->execute('security', new Request(), Context::createDefaultContext());

        static::assertEquals('', $response->headers->get('Content-Security-Policy'), '');
        static::assertEquals('sameorigin', $response->headers->get('X-Frame-Options'));
    }

    public function testSecurityNonce(): void
    {
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CSP_NONCE, 'NONCE123');
        $requestStack = $this->getContainer()->get(RequestStack::class);
        $requestStack->push($request);

        $response = $this->scripController->execute('security-nonce', $request, Context::createDefaultContext());

        static::assertEquals('NONCE123', $response->getContent());
    }

    public function testSnippet(): void
    {
        $translator = $this->getContainer()->get(\Shopware\Core\Framework\Adapter\Translation\Translator::class);

        $translator->setLocale('de-DE');
        $response = $this->scripController->execute('snippet', new Request(), Context::createDefaultContext());

        static::assertEquals('Beschreibung', $response->getContent());
    }

    public function testAclDenied(): void
    {
        $source = new AdminApiSource(null);

        static::expectException(PermissionDeniedException::class);

        $this->scripController->execute('raw', new Request(['input' => '<pre']), Context::createDefaultContext($source));
    }

    public function testAclAllowed(): void
    {
        $source = new AdminApiSource(null);
        $source->setPermissions(['app.AdminScript']);
        $response = $this->scripController->execute('raw', new Request(['input' => '<pre']), Context::createDefaultContext($source));
        static::assertEquals('text/xml', $response->headers->get('Content-Type'));

        $source = new AdminApiSource(null);
        $source->setPermissions(['app.all']);
        $response = $this->scripController->execute('raw', new Request(['input' => '<pre']), Context::createDefaultContext($source));
        static::assertNotEmpty($response->getContent());

        $source = new AdminApiSource(null);
        $source->setIsAdmin(true);
        $response = $this->scripController->execute('raw', new Request(['input' => '<pre']), Context::createDefaultContext($source));
        static::assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testAclAllowedIntegrationId(): void
    {
        /** @var Script[] $scripts */
        $scripts = $this->getContainer()->get(ScriptLoader::class)->get('admin-raw');
        $integrationId = null;
        foreach ($scripts as $script) {
            $integrationId = $script->getScriptAppInformation()->getIntegrationId();

            break;
        }

        $source = new AdminApiSource(null, $integrationId);
        $response = $this->scripController->execute('raw', new Request(['input' => '<pre']), Context::createDefaultContext($source));
        static::assertStringContainsString('<xml>', $response->getContent());
    }

    public function testScriptResponseEncoderJson(): void
    {
        $productId = $this->createProductForScriptResponseEncoderTest();
        $response = $this->scripController->execute('get-product', new Request(['productId' => $productId]), Context::createDefaultContext());
        $responseData = json_decode($response->getContent(), true);

        static::assertEquals($productId, $responseData['product']['id'] ?? null);
        static::assertEquals('Product', $responseData['product']['name'] ?? null);
        static::assertEquals(Defaults::CURRENCY, $responseData['price']['currencyId'] ?? null);
        static::assertEquals(10, $responseData['price']['gross'] ?? null);
    }

    public function testScriptResponseEncoderIncludes(): void
    {
        $productId = $this->createProductForScriptResponseEncoderTest();
        $request = new Request([
            'productId' => $productId,
            'includes' => [
                'product' => ['id', 'productNumber'],
                'price' => ['currencyId', 'gross'],
            ],
        ]);

        $response = $this->scripController->execute('get-product', $request, Context::createDefaultContext());
        $responseData = json_decode($response->getContent(), true);

        $productKeys = array_keys($responseData['product'] ?? []);
        sort($productKeys);
        static::assertEquals(['apiAlias', 'id', 'productNumber'], $productKeys);

        $priceKeys = array_keys($responseData['price'] ?? []);
        sort($priceKeys);
        static::assertEquals(['apiAlias', 'currencyId', 'gross'], $priceKeys);
    }

    private function createProductForScriptResponseEncoderTest(): string
    {
        $idsCollection = new IdsCollection();
        $builder = new ProductBuilder($idsCollection, 'product');
        $builder->price(10);
        $builder->translation(Defaults::LANGUAGE_SYSTEM, 'name', 'Product');
        $builder->write($this->getContainer());

        return $idsCollection->get('product');
    }
}
