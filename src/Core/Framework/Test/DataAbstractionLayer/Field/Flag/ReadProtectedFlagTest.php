<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field\Flag;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;

class ReadProtectedFlagTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AdminApiTestBehaviour;
    use SalesChannelApiTestBehaviour;

    public function testReadWithoutPermissionForAdminSourceWithJsonApiType(): void
    {
        $id = Uuid::randomHex();
        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('media.repository');

        $data = [
            'id' => $id,
        ];

        $repository->create([$data], Context::createDefaultContext());

        $url = sprintf(
            '/api/v%s/media',
            PlatformRequest::API_VERSION
        );

        $this->getBrowser()->request('GET', $url);

        $data = json_decode($this->getBrowser()->getResponse()->getContent(), true);
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
        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('media.repository');

        $data = [
            'id' => $id,
        ];

        $repository->create([$data], Context::createDefaultContext());

        $url = sprintf(
            '/api/v%s/media',
            PlatformRequest::API_VERSION
        );

        $browser = $this->getBrowser();
        $browser->setServerParameter('HTTP_ACCEPT', 'application/json');
        $browser->request('GET', $url);

        $data = json_decode($browser->getResponse()->getContent(), true);
        static::assertArrayHasKey('data', $data, print_r($data, true));

        $data = $data['data'];
        static::assertArrayNotHasKey('thumbnailsRo', $data[0]);
        static::assertArrayNotHasKey('mediaTypeRaw', $data[0]);
        static::assertArrayHasKey('userId', $data[0]);
        static::assertArrayHasKey('fileName', $data[0]);
    }

    public function testReadWithoutPermissionForSalesChannelSourceWithJsonApiType(): void
    {
        $id = Uuid::randomHex();
        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('product.repository');

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

        $repository->create([$data], Context::createDefaultContext());

        $url = sprintf(
            '/sales-channel-api/v%s/product?associations[cover][]',
            PlatformRequest::API_VERSION
        );

        $browser->request('GET', $url);
        $data = json_decode($browser->getResponse()->getContent(), true);
        static::assertArrayHasKey('included', $data, print_r($data, true));

        foreach ($data['included'] as $included) {
            if (!array_key_exists('type', $included) || $included['type'] !== 'media') {
                continue;
            }
            static::assertArrayNotHasKey('thumbnailsRo', $included['attributes']);
            static::assertArrayNotHasKey('mediaTypeRaw', $included['attributes']);
            static::assertArrayNotHasKey('userId', $included['attributes']);
            static::assertArrayHasKey('fileName', $included['attributes']);

            return;
        }

        static::fail('Unable to find included with type "media"');
    }

    public function testReadWithoutPermissionForSalesChannelSourceWithJsonType(): void
    {
        $id = Uuid::randomHex();
        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('product.repository');

        // when we create a salesChannelBrowser we also create a new SalesChannel,
        // we need the id of the salesChannel for the visibilities
        $browser = $this->getSalesChannelBrowser();

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

        $repository->create([$data], Context::createDefaultContext());

        $url = sprintf(
            '/sales-channel-api/v%s/product?associations[cover][]',
            PlatformRequest::API_VERSION
        );

        $browser->request('GET', $url);
        $data = json_decode($browser->getResponse()->getContent(), true);
        static::assertArrayHasKey('data', $data, print_r($data, true));
        $data = $data['data'];

        $product = $data[0];
        static::assertArrayHasKey('cover', $product);
        static::assertArrayHasKey('media', $product['cover']);

        $media = $product['cover']['media'];
        static::assertArrayNotHasKey('thumbnailsRo', $media);
        static::assertArrayNotHasKey('mediaTypeRaw', $media);
        static::assertArrayNotHasKey('userId', $media);
        static::assertArrayHasKey('fileName', $media);
    }
}
