<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Field\Flag;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class ApiAwareFlagTest extends TestCase
{
    use AdminApiTestBehaviour;
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private EntityRepository $mediaRepository;

    private EntityRepository $productRepository;

    protected function setUp(): void
    {
        $this->mediaRepository = $this->getContainer()->get('media.repository');
        $this->productRepository = $this->getContainer()->get('product.repository');
    }

    public function testReadWithoutPermissionForAdminSourceWithJsonApiType(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
        ];

        $this->mediaRepository->create([$data], Context::createDefaultContext());

        $url = '/api/media';

        $browser = $this->getBrowser();
        $browser->request('GET', $url);

        $response = $browser->getResponse();
        static::assertNotNull($response);
        static::assertIsString($response->getContent());

        $data = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('data', $data, print_r($data, true));

        $data = $data['data'];
        static::assertArrayNotHasKey('thumbnailsRo', $data[0]['attributes']);
        static::assertArrayNotHasKey('mediaTypeRaw', $data[0]['attributes']);
        static::assertArrayHasKey('userId', $data[0]['attributes']);
        static::assertArrayHasKey('fileName', $data[0]['attributes']);
    }

    public function testReadWithoutPermissionForAdminSourceWithJsonType(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
        ];

        $this->mediaRepository->create([$data], Context::createDefaultContext());

        $url = '/api/media';

        $browser = $this->getBrowser();
        $browser->setServerParameter('HTTP_ACCEPT', 'application/json');
        $browser->request('GET', $url);

        $response = $browser->getResponse();
        static::assertNotNull($response);
        static::assertIsString($response->getContent());

        $data = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('data', $data, print_r($data, true));

        $data = $data['data'];
        static::assertArrayNotHasKey('thumbnailsRo', $data[0]);
        static::assertArrayNotHasKey('mediaTypeRaw', $data[0]);
        static::assertArrayHasKey('userId', $data[0]);
        static::assertArrayHasKey('fileName', $data[0]);
    }

    public function testReadWithoutPermissionForSalesChannelSourceWithJsonType(): void
    {
        $id = Uuid::randomHex();

        // when we create a salesChannelBrowser we also create a new SalesChannel,
        // we need the id of the salesChannel for the visibilities
        $browser = $this->getSalesChannelBrowser();
        $browser->setServerParameter('HTTP_ACCEPT', 'application/vnd.api+json');

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'name' => 'test',
            'stock' => 1,
            'active' => true,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['taxRate' => 13, 'name' => 'green'],
            'cover' => [
                'id' => Uuid::randomHex(),
                'media' => [
                    'id' => Uuid::randomHex(),
                ],
            ],
            'visibilities' => [
                [
                    'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                    'salesChannelId' => $this->salesChannelIds[0],
                ],
            ],
        ];

        $this->productRepository->create([$data], Context::createDefaultContext());

        $url = '/store-api/product?associations[cover][]';

        $browser->request('GET', $url);
        $response = $browser->getResponse();
        static::assertNotNull($response);
        static::assertIsString($response->getContent());

        $data = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('elements', $data, print_r($data, true));

        foreach ($data['elements'] as $product) {
            static::assertArrayNotHasKey('thumbnailsRo', $product['cover']['media']);
            static::assertArrayNotHasKey('mediaTypeRaw', $product['cover']['media']);
            static::assertArrayNotHasKey('userId', $product['cover']['media']);
            static::assertArrayHasKey('fileName', $product['cover']['media']);
        }
    }
}
