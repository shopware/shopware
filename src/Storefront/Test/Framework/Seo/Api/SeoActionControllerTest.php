<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Seo\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Framework\Seo\Exception\SeoUrlRouteNotFoundException;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\ProductPageSeoUrlRoute;
use Shopware\Storefront\Framework\Seo\SeoUrlTemplate\SeoUrlTemplateEntity;

class SeoActionControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function testValidateEmpty(): void
    {
        $this->getBrowser()->request('POST', '/api/v1/_action/seo-url-template/validate');
        $response = $this->getBrowser()->getResponse();
        $result = json_decode($response->getContent(), true);

        static::assertNotEmpty($result['errors']);
        static::assertEquals(400, $response->getStatusCode());
    }

    public function testValidateInvalid(): void
    {
        $template = new SeoUrlTemplateEntity();
        $template->setRouteName('frontend.detail.page');
        $template->setTemplate('{{ product.name }');
        $template->setEntityName($this->getContainer()->get(ProductDefinition::class)->getEntityName());
        $template->setSalesChannelId(Defaults::SALES_CHANNEL);

        $this->getBrowser()->request('POST', '/api/v1/_action/seo-url-template/validate', $template->jsonSerialize());
        $response = $this->getBrowser()->getResponse();
        $result = json_decode($response->getContent(), true);

        static::assertNotEmpty($result['errors'] ?? []);
        static::assertEquals(400, $response->getStatusCode());
    }

    public function testValidateValid(): void
    {
        $template = new SeoUrlTemplateEntity();
        $template->setRouteName('frontend.detail.page');
        $template->setTemplate('{{ product.name }}');
        $template->setEntityName($this->getContainer()->get(ProductDefinition::class)->getEntityName());
        $template->setSalesChannelId(Defaults::SALES_CHANNEL);

        $this->getBrowser()->request('POST', '/api/v1/_action/seo-url-template/validate', $template->jsonSerialize());
        $response = $this->getBrowser()->getResponse();
        $result = json_decode($response->getContent(), true);

        static::assertArrayNotHasKey('errors', $result);
        static::assertEquals(200, $response->getStatusCode());
    }

    public function testGetSeoContext(): void
    {
        $product = [
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'name' => 'test',
            'price' => [
                'gross' => 10,
                'net' => 20,
                'linked' => false,
            ],
            'manufacturer' => [
                'id' => Uuid::randomHex(),
                'name' => 'test',
            ],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'stock' => 0,
        ];
        $this->getBrowser()->request('POST', '/api/v1/product', $product);

        $data = [
            'routeName' => ProductPageSeoUrlRoute::ROUTE_NAME,
            'entityName' => $this->getContainer()->get(ProductDefinition::class)->getEntityName(),
        ];
        $this->getBrowser()->request('POST', '/api/v1/_action/seo-url-template/context', $data);

        $response = $this->getBrowser()->getResponse();
        static::assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        static::assertNotNull($data['product'] ?? null);
    }

    public function testPreview(): void
    {
        $product = [
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'name' => 'foo bar',
            'price' => [
                'gross' => 10,
                'net' => 20,
                'linked' => false,
            ],
            'manufacturer' => [
                'id' => Uuid::randomHex(),
                'name' => 'test',
            ],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'stock' => 0,
        ];
        $this->getBrowser()->request('POST', '/api/v1/product', $product);

        $data = [
            'routeName' => ProductPageSeoUrlRoute::ROUTE_NAME,
            'entityName' => $this->getContainer()->get(ProductDefinition::class)->getEntityName(),
            'template' => '{{ product.name }}',
            'salesChannelId' => null,
        ];
        $this->getBrowser()->request('POST', '/api/v1/_action/seo-url-template/preview', $data);

        $response = $this->getBrowser()->getResponse();
        static::assertEquals(200, $response->getStatusCode(), $response->getContent());

        $data = json_decode($response->getContent(), true);

        static::assertEquals('foo-bar', $data[0]['seoPathInfo']);
    }

    public function testUnknownRoute(): void
    {
        $template = new SeoUrlTemplateEntity();
        $template->setRouteName('unknown.route');
        $template->setTemplate('{{ product.name }}');
        $template->setEntityName($this->getContainer()->get(ProductDefinition::class)->getEntityName());
        $template->setSalesChannelId(Defaults::SALES_CHANNEL);

        $this->getBrowser()->request('POST', '/api/v1/_action/seo-url-template/validate', $template->jsonSerialize());
        $response = $this->getBrowser()->getResponse();
        $result = json_decode($response->getContent(), true);

        static::assertArrayHasKey('errors', $result);
        static::assertEquals(404, $response->getStatusCode());

        static::assertEquals(SeoUrlRouteNotFoundException::ERROR_CODE, $result['errors'][0]['code']);
    }
}
