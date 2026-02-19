<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Hydration\DataLoader\ShippingMethodLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Shipping\SalesChannel\AbstractShippingMethodRoute;
use Shopware\Core\Checkout\Shipping\SalesChannel\ShippingMethodRouteResponse;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\ShippingMethodLoader\ShippingMethodDataLoader;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\ShippingMethodLoader\ShippingMethodLoaderConfig;
use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Content\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(ShippingMethodDataLoader::class)]
class ShippingMethodDataLoaderTest extends TestCase
{
    private AbstractShippingMethodRoute&MockObject $shippingMethodRoute;

    private ShippingMethodDataLoader $dataLoader;

    protected function setUp(): void
    {
        $this->shippingMethodRoute = $this->createMock(AbstractShippingMethodRoute::class);
        $this->dataLoader = new ShippingMethodDataLoader($this->shippingMethodRoute);
    }

    #[TestDox('returns shipping_method source type identifier')]
    public function testGetRequirementTypeReturnsShippingMethodString(): void
    {
        static::assertSame('shipping_method', ShippingMethodDataLoader::getRequirementType());
    }

    #[TestDox('loads shipping methods and returns cachedExternally result with empty cache tags')]
    public function testLoadReturnsCachedExternallyResult(): void
    {
        $shippingMethods = new ShippingMethodCollection();
        $response = $this->createShippingMethodRouteResponse($shippingMethods);

        $element = new ContentElement(id: 'element-id', component: 'test');
        $config = new ShippingMethodLoaderConfig();
        $requirement = new DataRequirement('shippingMethodKey', 'shipping_method', $config);
        $context = Generator::generateSalesChannelContext();
        $request = new Request();

        $this->shippingMethodRoute
            ->method('load')
            ->with(
                static::callback(function (Request $clonedRequest): bool {
                    static::assertTrue($clonedRequest->query->get('onlyAvailable'));

                    return true;
                }),
                static::anything(),
                static::anything()
            )
            ->willReturn($response);

        $result = $this->dataLoader->load($element, $requirement, $context, $request);

        static::assertTrue($result->hasData());
        static::assertSame($shippingMethods, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('adds associations from ShippingMethodLoaderConfig to criteria')]
    public function testLoadAddsAssociationsFromConfigToCriteria(): void
    {
        $shippingMethods = new ShippingMethodCollection();
        $response = $this->createShippingMethodRouteResponse($shippingMethods);

        $element = new ContentElement(id: 'element-id', component: 'test');
        $config = new ShippingMethodLoaderConfig(associations: ['country', 'translations']);
        $requirement = new DataRequirement('shippingMethodKey', 'shipping_method', $config);
        $context = Generator::generateSalesChannelContext();

        $this->shippingMethodRoute
            ->method('load')
            ->with(
                static::anything(),
                static::anything(),
                static::callback(function (Criteria $criteria): bool {
                    static::assertContains('country', array_keys($criteria->getAssociations()));
                    static::assertContains('translations', array_keys($criteria->getAssociations()));

                    return true;
                })
            )
            ->willReturn($response);

        $result = $this->dataLoader->load($element, $requirement, $context, new Request());

        static::assertTrue($result->hasData());
        static::assertSame($shippingMethods, $result->data);
    }

    #[TestDox('sets onlyAvailable false on cloned request when config has onlyAvailable false')]
    public function testLoadSetsOnlyAvailableFalseOnClonedRequest(): void
    {
        $shippingMethods = new ShippingMethodCollection();
        $response = $this->createShippingMethodRouteResponse($shippingMethods);

        $element = new ContentElement(id: 'element-id', component: 'test');
        $config = new ShippingMethodLoaderConfig(onlyAvailable: false);
        $requirement = new DataRequirement('shippingMethodKey', 'shipping_method', $config);
        $context = Generator::generateSalesChannelContext();
        $originalRequest = new Request();

        $this->shippingMethodRoute
            ->method('load')
            ->with(
                static::callback(function (Request $clonedRequest) use ($originalRequest): bool {
                    static::assertNotSame($originalRequest, $clonedRequest);
                    static::assertFalse($clonedRequest->query->get('onlyAvailable'));

                    return true;
                }),
                static::anything(),
                static::anything()
            )
            ->willReturn($response);

        $result = $this->dataLoader->load($element, $requirement, $context, $originalRequest);

        static::assertTrue($result->hasData());
        static::assertSame($shippingMethods, $result->data);
    }

    #[TestDox('loads shipping methods without associations and defaults onlyAvailable to true when config is not a ShippingMethodLoaderConfig instance')]
    public function testLoadWithNonShippingMethodLoaderConfigSkipsConfigSpecificLogic(): void
    {
        $shippingMethods = new ShippingMethodCollection();
        $response = $this->createShippingMethodRouteResponse($shippingMethods);

        $element = new ContentElement(id: 'element-id', component: 'test');
        $wrongConfig = static::createStub(AbstractContentDataLoaderConfig::class);
        $requirement = new DataRequirement('shippingMethodKey', 'shipping_method', $wrongConfig);
        $context = Generator::generateSalesChannelContext();
        $originalRequest = new Request();

        $this->shippingMethodRoute
            ->method('load')
            ->willReturn($response);

        $result = $this->dataLoader->load($element, $requirement, $context, $originalRequest);

        static::assertTrue($result->hasData());
        static::assertSame($shippingMethods, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('throws DecorationPatternException when getDecorated is called')]
    public function testGetDecoratedThrowsDecorationPatternException(): void
    {
        $this->expectException(DecorationPatternException::class);
        $this->expectExceptionMessage('The getDecorated() function of core class');

        $this->dataLoader->getDecorated();
    }

    private function createShippingMethodRouteResponse(ShippingMethodCollection $shippingMethods): ShippingMethodRouteResponse
    {
        $response = static::createStub(ShippingMethodRouteResponse::class);
        $response->method('getShippingMethods')->willReturn($shippingMethods);

        return $response;
    }
}
