<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\SalesChannel\Entity;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReadProtected;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class SalesChannelApiControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    /**
     * @var KernelBrowser
     */
    private $browser;

    /**
     * @var string
     */
    private $salesChannelId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->salesChannelId = Uuid::randomHex();
        $this->browser = $this->createCustomSalesChannelBrowser(['id' => $this->salesChannelId]);
    }

    // test for read protection of term queries can be found in
    // platform/src/Core/Framework/Test/DataAbstractionLayer/Search/Term/EntityScoreBuilderTest.php

    public function testFilterRestrictedAssociation(): void
    {
        $id = $this->createTestData();

        $this->browser->request(
            'GET',
            '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/product/' . $id . '?filter[visibilities.id]=' . Uuid::randomHex()
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true);
        static::assertArrayHasKey('errors', $response);
        static::assertSame($response['errors'][0]['code'], 'FRAMEWORK__READ_PROTECTED');
    }

    public function testPostFilterRestrictedAssociation(): void
    {
        $id = $this->createTestData();

        $this->browser->request(
            'GET',
            '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/product/' . $id . '?post-filter[visibilities.id]=' . Uuid::randomHex()
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true);
        static::assertArrayHasKey('errors', $response);
        static::assertSame($response['errors'][0]['code'], 'FRAMEWORK__READ_PROTECTED');
    }

    public function testLoadManyToManyAssociation(): void
    {
        $id = $this->createTestData();

        $this->browser->request(
            'GET',
            '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/product/' . $id . '?associations[options][associations][group][]'
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true);
        static::assertArrayNotHasKey('errors', $response);
    }

    public function testLoadRestrictedAssociation(): void
    {
        $id = $this->createTestData();

        $this->browser->request(
            'GET',
            '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/product/' . $id . '?associations[visibilities][]=' . Uuid::randomHex()
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true);
        static::assertArrayHasKey('errors', $response);
        static::assertSame($response['errors'][0]['code'], 'FRAMEWORK__READ_PROTECTED');
    }

    public function testSortRestrictedAssociation(): void
    {
        $id = $this->createTestData();

        $this->browser->request(
            'GET',
            '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/product/' . $id . '?sort=visibilities.id'
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('errors', $response);
        static::assertSame($response['errors'][0]['code'], 'FRAMEWORK__READ_PROTECTED');
    }

    public function testAggregateRestrictedField(): void
    {
        $id = $this->createTestData();

        $this->browser->request(
            'GET',
            '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/product/' . $id
            . '?aggregations[0][name]=test&aggregations[0][field]=visibilities.id&aggregations[0][type]=count'
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('errors', $response);
        static::assertSame('FRAMEWORK__READ_PROTECTED', $response['errors'][0]['code']);
    }

    public function testRestrictedFilterForAssociation(): void
    {
        $id = $this->createTestData();

        $this->browser->request(
            'GET',
            '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/product/' . $id
            . '?associations[categories][]&associations[categories][filter][navigationSalesChannels.id]=' . $id
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('errors', $response);
        static::assertSame($response['errors'][0]['code'], 'FRAMEWORK__READ_PROTECTED');
    }

    private function createTestData(): string
    {
        $definitionRegistry = $this->getContainer()->get(DefinitionInstanceRegistry::class);
        $productDefinition = $definitionRegistry->get(ProductDefinition::class);

        static::assertNotNull($productDefinition->getField('searchKeywords'));

        $flag = $productDefinition->getField('searchKeywords')->getFlag(ReadProtected::class);
        static::assertNotNull($flag);
        static::assertFalse($flag->isSourceAllowed(SalesChannelApiSource::class));

        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 10,
            'active' => true,
            'name' => 'test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 99, 'net' => 99, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 99],
            'categories' => [
                ['id' => $id, 'name' => 'asd'],
            ],
            'visibilities' => [
                [
                    'salesChannelId' => $this->salesChannelId,
                    'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
        ];

        $this->getContainer()->get('product.repository')->create([$data], Context::createDefaultContext());

        return $id;
    }
}
