<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Seo\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Framework\Seo\SeoUrlGenerator\ProductDetailPageSeoUrlGenerator;
use Shopware\Storefront\Framework\Seo\SeoUrlTemplate\SeoUrlTemplateEntity;

class SeoControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function testValidateEmpty(): void
    {
        $this->getClient()->request('POST', '/api/v1/_action/seo-url-template/validate');
        $response = $this->getClient()->getResponse();
        $result = json_decode($response->getContent(), true);

        static::assertNotEmpty($result['errors']);
        static::assertEquals(400, $response->getStatusCode());
    }

    public function testValidateInvalid(): void
    {
        $template = new SeoUrlTemplateEntity();
        $template->setRouteName('frontend.detail.page');
        $template->setTemplate('{{ product.name }');
        $template->setEntityName(ProductDefinition::getEntityName());
        $template->setSalesChannelId(Defaults::SALES_CHANNEL);

        $this->getClient()->request('POST', '/api/v1/_action/seo-url-template/validate', $template->jsonSerialize());
        $response = $this->getClient()->getResponse();
        $result = json_decode($response->getContent(), true);

        static::assertNotEmpty($result['errors'] ?? []);
        static::assertEquals(400, $response->getStatusCode());
    }

    public function testValidateValid(): void
    {
        $template = new SeoUrlTemplateEntity();
        $template->setRouteName('frontend.detail.page');
        $template->setTemplate('{{ product.name }}');
        $template->setEntityName(ProductDefinition::getEntityName());
        $template->setSalesChannelId(Defaults::SALES_CHANNEL);

        $this->getClient()->request('POST', '/api/v1/_action/seo-url-template/validate', $template->jsonSerialize());
        $response = $this->getClient()->getResponse();
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
        $this->getClient()->request('POST', '/api/v1/product', $product);

        $data = [
            'routeName' => ProductDetailPageSeoUrlGenerator::ROUTE_NAME,
            'entityName' => ProductDefinition::getEntityName(),
        ];
        $this->getClient()->request('POST', '/api/v1/_action/seo-url-template/context', $data);

        $response = $this->getClient()->getResponse();
        static::assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $expectedKeys = [
            'product',
            'id',
            'productId',
            'shortId',
            'productName',
            'manufacturerId',
            'manufacturerName',
            'manufacturerNumber',
        ];
        $actualKeys = array_keys($data);
        sort($expectedKeys);
        sort($actualKeys);
        static::assertEquals($expectedKeys, $actualKeys);
    }
}
