<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment\ContentSystem\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\ContentSystem\DataLoader\PaymentMethodDataLoader;
use Shopware\Core\Checkout\Payment\ContentSystem\DataLoader\PaymentMethodLoaderConfig;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
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

    #[TestDox('resolves provided data type from annotation')]
    public function testGetProvidedDataResolvesExpectedType(): void
    {
        $descriptor = PaymentMethodDataLoader::getProvidedData();

        static::assertSame(PaymentMethodCollection::class, $descriptor->className);
        static::assertSame([], $descriptor->genericParameters);
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
            ->expects($this->once())
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

        $this->dataLoader->load($element, $requirement, $context, new Request());
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
                static::callback(function (Request $clonedRequest): bool {
                    static::assertFalse($clonedRequest->query->get('onlyAvailable'));

                    return true;
                }),
                static::anything(),
                static::anything()
            )
            ->willReturn($response);

        $this->dataLoader->load($element, $requirement, $context, new Request());
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
