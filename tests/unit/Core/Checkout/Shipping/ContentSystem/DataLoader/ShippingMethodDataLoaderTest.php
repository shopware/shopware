<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Shipping\ContentSystem\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Shipping\ContentSystem\DataLoader\ShippingMethodDataLoader;
use Shopware\Core\Checkout\Shipping\ContentSystem\DataLoader\ShippingMethodLoaderConfig;
use Shopware\Core\Checkout\Shipping\SalesChannel\AbstractShippingMethodRoute;
use Shopware\Core\Checkout\Shipping\SalesChannel\ShippingMethodRouteResponse;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ShippingMethodDataLoader::class)]
class ShippingMethodDataLoaderTest extends TestCase
{
    private ShippingMethodDataLoader $dataLoader;

    protected function setUp(): void
    {
        $this->dataLoader = new ShippingMethodDataLoader(static::createStub(AbstractShippingMethodRoute::class));
    }

    #[TestDox('returns shipping_method source type identifier')]
    public function testGetRequirementTypeReturnsShippingMethodString(): void
    {
        static::assertSame('shipping_method', ShippingMethodDataLoader::getRequirementType());
    }

    #[TestDox('declares ShippingMethodCollection as its single producible type')]
    public function testProducibleTypesDeclaresExtendsType(): void
    {
        $capabilities = $this->dataLoader->producibleTypes();

        static::assertCount(1, $capabilities);
        static::assertSame(ShippingMethodCollection::class, $capabilities[0]->producedType);
        static::assertSame([], $capabilities[0]->genericParameters);
        static::assertSame([], $capabilities[0]->configTemplate);
    }

    #[TestDox('loads shipping methods and returns cachedExternally result with empty cache tags')]
    public function testLoadReturnsCachedExternallyResult(): void
    {
        $shippingMethods = new ShippingMethodCollection();
        $response = $this->createShippingMethodRouteResponse($shippingMethods);

        $context = Generator::generateSalesChannelContext();

        $shippingMethodRoute = static::createStub(AbstractShippingMethodRoute::class);
        $shippingMethodRoute->method('load')->willReturn($response);

        $dataLoader = new ShippingMethodDataLoader($shippingMethodRoute);
        $result = $dataLoader->load(
            new LoaderInputs(['associations' => [], 'onlyAvailable' => true]),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertTrue($result->hasData());
        static::assertSame($shippingMethods, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('sets onlyAvailable true on cloned request when the onlyAvailable input is true')]
    public function testLoadSetsOnlyAvailableTrueByDefaultOnClonedRequest(): void
    {
        $shippingMethods = new ShippingMethodCollection();
        $response = $this->createShippingMethodRouteResponse($shippingMethods);

        $context = Generator::generateSalesChannelContext();

        $capturedRequest = null;
        $shippingMethodRoute = static::createStub(AbstractShippingMethodRoute::class);
        $shippingMethodRoute->method('load')->willReturnCallback(
            function (Request $clonedRequest) use (&$capturedRequest, $response): ShippingMethodRouteResponse {
                $capturedRequest = $clonedRequest;

                return $response;
            }
        );

        $dataLoader = new ShippingMethodDataLoader($shippingMethodRoute);
        $result = $dataLoader->load(
            new LoaderInputs(['associations' => [], 'onlyAvailable' => true]),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertInstanceOf(Request::class, $capturedRequest);
        static::assertTrue($capturedRequest->query->get('onlyAvailable'));
        static::assertTrue($result->hasData());
        static::assertSame($shippingMethods, $result->data);
    }

    #[TestDox('adds the associations input to criteria')]
    public function testLoadAddsAssociationsFromConfigToCriteria(): void
    {
        $shippingMethods = new ShippingMethodCollection();
        $response = $this->createShippingMethodRouteResponse($shippingMethods);

        $context = Generator::generateSalesChannelContext();

        $capturedCriteria = null;
        $shippingMethodRoute = static::createStub(AbstractShippingMethodRoute::class);
        $shippingMethodRoute->method('load')->willReturnCallback(
            function (Request $clonedRequest, SalesChannelContext $context, Criteria $criteria) use (&$capturedCriteria, $response): ShippingMethodRouteResponse {
                $capturedCriteria = $criteria;

                return $response;
            }
        );

        $dataLoader = new ShippingMethodDataLoader($shippingMethodRoute);
        $result = $dataLoader->load(
            new LoaderInputs(['associations' => ['country', 'translations'], 'onlyAvailable' => true]),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertContains('country', array_keys($capturedCriteria->getAssociations()));
        static::assertContains('translations', array_keys($capturedCriteria->getAssociations()));
        static::assertTrue($result->hasData());
        static::assertSame($shippingMethods, $result->data);
    }

    #[TestDox('sets onlyAvailable false on cloned request when the onlyAvailable input is false')]
    public function testLoadSetsOnlyAvailableFalseOnClonedRequest(): void
    {
        $shippingMethods = new ShippingMethodCollection();
        $response = $this->createShippingMethodRouteResponse($shippingMethods);

        $context = Generator::generateSalesChannelContext();
        $originalRequest = new Request();

        $capturedRequest = null;
        $shippingMethodRoute = static::createStub(AbstractShippingMethodRoute::class);
        $shippingMethodRoute->method('load')->willReturnCallback(
            function (Request $clonedRequest) use (&$capturedRequest, $response): ShippingMethodRouteResponse {
                $capturedRequest = $clonedRequest;

                return $response;
            }
        );

        $dataLoader = new ShippingMethodDataLoader($shippingMethodRoute);
        $result = $dataLoader->load(
            new LoaderInputs(['associations' => [], 'onlyAvailable' => false]),
            self::requirement(),
            $context,
            $originalRequest,
        );

        static::assertInstanceOf(Request::class, $capturedRequest);
        static::assertNotSame($originalRequest, $capturedRequest);
        static::assertFalse($capturedRequest->query->get('onlyAvailable'));
        static::assertTrue($result->hasData());
        static::assertSame($shippingMethods, $result->data);
    }

    private static function requirement(): DataRequirement
    {
        return new DataRequirement('shippingMethodKey', 'shipping_method', new ShippingMethodLoaderConfig());
    }

    private function createShippingMethodRouteResponse(ShippingMethodCollection $shippingMethods): ShippingMethodRouteResponse
    {
        $response = static::createStub(ShippingMethodRouteResponse::class);
        $response->method('getShippingMethods')->willReturn($shippingMethods);

        return $response;
    }
}
