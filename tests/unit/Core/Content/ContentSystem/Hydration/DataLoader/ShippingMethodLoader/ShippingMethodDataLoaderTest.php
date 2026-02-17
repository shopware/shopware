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
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
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

    #[TestDox('throws DecorationPatternException when getDecorated is called')]
    public function testGetDecoratedThrowsDecorationPatternException(): void
    {
        $this->expectException(DecorationPatternException::class);
        $this->expectExceptionMessage('The getDecorated() function of core class');

        $this->dataLoader->getDecorated();
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
                static::isInstanceOf(Request::class),
                $context,
                static::callback(static function (Criteria $criteria): bool {
                    $associations = $criteria->getAssociations();

                    return isset($associations['country']) && isset($associations['translations']);
                })
            )
            ->willReturn($response);

        $result = $this->dataLoader->load($element, $requirement, $context, new Request());

        static::assertTrue($result->hasData());
        static::assertSame($shippingMethods, $result->data);
    }

    #[TestDox('sets onlyAvailable true on cloned request when config has onlyAvailable true')]
    public function testLoadSetsOnlyAvailableTrueOnClonedRequest(): void
    {
        $shippingMethods = new ShippingMethodCollection();
        $response = $this->createShippingMethodRouteResponse($shippingMethods);

        $element = new ContentElement(id: 'element-id', component: 'test');
        $config = new ShippingMethodLoaderConfig(onlyAvailable: true);
        $requirement = new DataRequirement('shippingMethodKey', 'shipping_method', $config);
        $context = Generator::generateSalesChannelContext();
        $originalRequest = new Request();

        $this->shippingMethodRoute
            ->method('load')
            ->with(
                static::callback(static function (Request $clonedRequest) use ($originalRequest): bool {
                    return $clonedRequest !== $originalRequest
                        && $clonedRequest->query->get('onlyAvailable') === true;
                }),
                $context,
                static::isInstanceOf(Criteria::class)
            )
            ->willReturn($response);

        $result = $this->dataLoader->load($element, $requirement, $context, $originalRequest);

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
                static::callback(static function (Request $clonedRequest) use ($originalRequest): bool {
                    return $clonedRequest !== $originalRequest
                        && $clonedRequest->query->get('onlyAvailable') === false;
                }),
                $context,
                static::isInstanceOf(Criteria::class)
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
            ->with(
                static::callback(static function (Request $clonedRequest) use ($originalRequest): bool {
                    return $clonedRequest !== $originalRequest
                        && $clonedRequest->query->get('onlyAvailable') === true
                        && $clonedRequest->query->count() === 1;
                }),
                $context,
                static::callback(static function (Criteria $criteria): bool {
                    return $criteria->getAssociations() === [];
                })
            )
            ->willReturn($response);

        $result = $this->dataLoader->load($element, $requirement, $context, $originalRequest);

        static::assertTrue($result->hasData());
        static::assertSame($shippingMethods, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    private function createShippingMethodRouteResponse(ShippingMethodCollection $shippingMethods): ShippingMethodRouteResponse
    {
        $response = static::createStub(ShippingMethodRouteResponse::class);
        $response->method('getShippingMethods')->willReturn($shippingMethods);

        return $response;
    }
}
