<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Hydration\DataLoader\PaymentMethodLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\PaymentMethodLoader\PaymentMethodDataLoader;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\PaymentMethodLoader\PaymentMethodLoaderConfig;
use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Content\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(PaymentMethodDataLoader::class)]
class PaymentMethodDataLoaderTest extends TestCase
{
    private AbstractPaymentMethodRoute&MockObject $paymentMethodRoute;

    private PaymentMethodDataLoader $dataLoader;

    protected function setUp(): void
    {
        $this->paymentMethodRoute = $this->createMock(AbstractPaymentMethodRoute::class);
        $this->dataLoader = new PaymentMethodDataLoader($this->paymentMethodRoute);
    }

    #[TestDox('returns payment_method source type identifier')]
    public function testGetRequirementTypeReturnsPaymentMethodString(): void
    {
        static::assertSame('payment_method', PaymentMethodDataLoader::getRequirementType());
    }

    #[TestDox('throws DecorationPatternException when getDecorated is called')]
    public function testGetDecoratedThrowsDecorationPatternException(): void
    {
        $this->expectException(DecorationPatternException::class);
        $this->expectExceptionMessage('The getDecorated() function of core class');

        $this->dataLoader->getDecorated();
    }

    #[TestDox('returns cachedExternally result with payment method collection')]
    public function testLoadReturnsCachedExternallyResult(): void
    {
        $paymentMethods = new PaymentMethodCollection();
        $context = Generator::generateSalesChannelContext();
        $response = new PaymentMethodRouteResponse(
            new EntitySearchResult(
                'payment_method',
                0,
                $paymentMethods,
                null,
                new Criteria(),
                $context->getContext()
            )
        );
        $element = new ContentElement(id: 'element-id', component: 'test');
        $config = new PaymentMethodLoaderConfig();
        $requirement = new DataRequirement('paymentMethodKey', 'payment_method', $config);
        $request = new Request();

        $this->paymentMethodRoute
            ->method('load')
            ->willReturn($response);

        $result = $this->dataLoader->load($element, $requirement, $context, $request);

        static::assertTrue($result->hasData());
        static::assertSame($paymentMethods, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('adds associations from PaymentMethodLoaderConfig to criteria')]
    public function testLoadAddsAssociationsFromConfigToCriteria(): void
    {
        $paymentMethods = new PaymentMethodCollection();
        $context = Generator::generateSalesChannelContext();
        $response = new PaymentMethodRouteResponse(
            new EntitySearchResult(
                'payment_method',
                0,
                $paymentMethods,
                null,
                new Criteria(),
                $context->getContext()
            )
        );
        $element = new ContentElement(id: 'element-id', component: 'test');
        $config = new PaymentMethodLoaderConfig(associations: ['country', 'translations']);
        $requirement = new DataRequirement('paymentMethodKey', 'payment_method', $config);

        $this->paymentMethodRoute
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
        static::assertSame($paymentMethods, $result->data);
    }

    #[TestDox('sets onlyAvailable query parameter from config on cloned request')]
    public function testLoadSetsOnlyAvailableParameterFromConfig(): void
    {
        $paymentMethods = new PaymentMethodCollection();
        $context = Generator::generateSalesChannelContext();
        $response = new PaymentMethodRouteResponse(
            new EntitySearchResult(
                'payment_method',
                0,
                $paymentMethods,
                null,
                new Criteria(),
                $context->getContext()
            )
        );
        $element = new ContentElement(id: 'element-id', component: 'test');
        $config = new PaymentMethodLoaderConfig(onlyAvailable: false);
        $requirement = new DataRequirement('paymentMethodKey', 'payment_method', $config);

        $this->paymentMethodRoute
            ->expects($this->once())
            ->method('load')
            ->with(
                static::callback(static function (Request $clonedRequest): bool {
                    return $clonedRequest->query->get('onlyAvailable') === false;
                }),
                $context,
                static::isInstanceOf(Criteria::class)
            )
            ->willReturn($response);

        $result = $this->dataLoader->load($element, $requirement, $context, new Request());

        static::assertTrue($result->hasData());
        static::assertSame($paymentMethods, $result->data);
    }

    #[TestDox('sets onlyAvailable to true by default when config does not specify it')]
    public function testLoadSetsOnlyAvailableTrueByDefault(): void
    {
        $paymentMethods = new PaymentMethodCollection();
        $context = Generator::generateSalesChannelContext();
        $response = new PaymentMethodRouteResponse(
            new EntitySearchResult(
                'payment_method',
                0,
                $paymentMethods,
                null,
                new Criteria(),
                $context->getContext()
            )
        );
        $element = new ContentElement(id: 'element-id', component: 'test');
        $config = new PaymentMethodLoaderConfig(onlyAvailable: true);
        $requirement = new DataRequirement('paymentMethodKey', 'payment_method', $config);

        $this->paymentMethodRoute
            ->expects($this->once())
            ->method('load')
            ->with(
                static::callback(static function (Request $clonedRequest): bool {
                    return $clonedRequest->query->get('onlyAvailable') === true;
                }),
                $context,
                static::isInstanceOf(Criteria::class)
            )
            ->willReturn($response);

        $result = $this->dataLoader->load($element, $requirement, $context, new Request());

        static::assertTrue($result->hasData());
        static::assertSame($paymentMethods, $result->data);
    }

    #[TestDox('skips config-specific logic when config is not a PaymentMethodLoaderConfig instance')]
    public function testLoadWithNonPaymentMethodLoaderConfigSkipsConfigSpecificLogic(): void
    {
        $paymentMethods = new PaymentMethodCollection();
        $context = Generator::generateSalesChannelContext();
        $response = new PaymentMethodRouteResponse(
            new EntitySearchResult(
                'payment_method',
                0,
                $paymentMethods,
                null,
                new Criteria(),
                $context->getContext()
            )
        );
        $element = new ContentElement(id: 'element-id', component: 'test');
        $wrongConfig = static::createStub(AbstractContentDataLoaderConfig::class);
        $requirement = new DataRequirement('paymentMethodKey', 'payment_method', $wrongConfig);

        $this->paymentMethodRoute
            ->method('load')
            ->with(
                static::isInstanceOf(Request::class),
                $context,
                static::callback(static function (Criteria $criteria): bool {
                    return $criteria->getAssociations() === [];
                })
            )
            ->willReturn($response);

        $result = $this->dataLoader->load($element, $requirement, $context, new Request());

        static::assertTrue($result->hasData());
        static::assertSame($paymentMethods, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('does not modify original request when cloning for onlyAvailable parameter')]
    public function testLoadDoesNotModifyOriginalRequest(): void
    {
        $paymentMethods = new PaymentMethodCollection();
        $context = Generator::generateSalesChannelContext();
        $response = new PaymentMethodRouteResponse(
            new EntitySearchResult(
                'payment_method',
                0,
                $paymentMethods,
                null,
                new Criteria(),
                $context->getContext()
            )
        );
        $element = new ContentElement(id: 'element-id', component: 'test');
        $config = new PaymentMethodLoaderConfig(onlyAvailable: false);
        $requirement = new DataRequirement('paymentMethodKey', 'payment_method', $config);
        $originalRequest = new Request();

        $this->paymentMethodRoute
            ->method('load')
            ->willReturn($response);

        $this->dataLoader->load($element, $requirement, $context, $originalRequest);

        static::assertNull($originalRequest->query->get('onlyAvailable'));
    }
}
