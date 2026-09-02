<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment\ContentSystem\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\ContentSystem\DataLoader\PaymentMethodDataLoader;
use Shopware\Core\Checkout\Payment\ContentSystem\DataLoader\PaymentMethodLoaderConfig;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Script\ScriptException;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentMethodDataLoader::class)]
class PaymentMethodDataLoaderTest extends TestCase
{
    private AbstractPaymentMethodRoute&Stub $paymentMethodRoute;

    private PaymentMethodDataLoader $dataLoader;

    protected function setUp(): void
    {
        $this->paymentMethodRoute = static::createStub(AbstractPaymentMethodRoute::class);
        $this->dataLoader = new PaymentMethodDataLoader($this->paymentMethodRoute);
    }

    #[TestDox('returns payment_method source type identifier')]
    public function testGetRequirementTypeReturnsPaymentMethodString(): void
    {
        static::assertSame('payment_method', PaymentMethodDataLoader::getRequirementType());
    }

    #[TestDox('declares PaymentMethodCollection as its single producible type')]
    public function testProducibleTypesDeclaresExtendsType(): void
    {
        $capabilities = $this->dataLoader->producibleTypes();

        static::assertCount(1, $capabilities);
        static::assertSame(PaymentMethodCollection::class, $capabilities[0]->producedType);
        static::assertSame([], $capabilities[0]->genericParameters);
        static::assertSame([], $capabilities[0]->configTemplate);
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
        $request = new Request();

        $this->paymentMethodRoute
            ->method('load')
            ->willReturn($response);

        $result = $this->dataLoader->load(
            new LoaderInputs(['associations' => [], 'onlyAvailable' => true]),
            self::requirement(),
            $context,
            $request,
        );

        static::assertTrue($result->hasData());
        static::assertSame($paymentMethods, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('adds the associations input to criteria')]
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
        $paymentMethodRoute = $this->createMock(AbstractPaymentMethodRoute::class);
        $paymentMethodRoute
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

        $dataLoader = new PaymentMethodDataLoader($paymentMethodRoute);
        $dataLoader->load(
            new LoaderInputs(['associations' => ['country', 'translations'], 'onlyAvailable' => true]),
            self::requirement(),
            $context,
            new Request(),
        );
    }

    #[TestDox('sets the onlyAvailable query parameter from the input on the cloned request')]
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
        $paymentMethodRoute = $this->createMock(AbstractPaymentMethodRoute::class);
        $paymentMethodRoute
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

        $dataLoader = new PaymentMethodDataLoader($paymentMethodRoute);
        $dataLoader->load(
            new LoaderInputs(['associations' => [], 'onlyAvailable' => false]),
            self::requirement(),
            $context,
            new Request(),
        );
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
        $originalRequest = new Request();

        $this->paymentMethodRoute
            ->method('load')
            ->willReturn($response);

        $this->dataLoader->load(
            new LoaderInputs(['associations' => [], 'onlyAvailable' => false]),
            self::requirement(),
            $context,
            $originalRequest,
        );

        static::assertNull($originalRequest->query->get('onlyAvailable'));
    }

    #[DataProvider('sampleDomainExceptionProvider')]
    #[TestDox('degrades to notFound when the payment method route throws the Shopware exception $_dataName')]
    public function testLoadReturnsNotFoundWhenPaymentMethodRouteThrows(\Throwable $exception): void
    {
        $context = Generator::generateSalesChannelContext();

        $paymentMethodRoute = $this->createMock(AbstractPaymentMethodRoute::class);
        $paymentMethodRoute
            ->expects($this->once())
            ->method('load')
            ->willThrowException($exception);

        $dataLoader = new PaymentMethodDataLoader($paymentMethodRoute);
        $result = $dataLoader->load(
            new LoaderInputs(['associations' => [], 'onlyAvailable' => true]),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertFalse($result->hasData());
        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('lets a TypeError from the payment method route propagate instead of degrading')]
    public function testLoadLetsThrowableOutsideShopwareHttpExceptionPropagate(): void
    {
        $context = Generator::generateSalesChannelContext();

        $typeError = new \TypeError('Argument #3 ($criteria) must be of type Criteria, null given');

        $paymentMethodRoute = $this->createMock(AbstractPaymentMethodRoute::class);
        $paymentMethodRoute
            ->expects($this->once())
            ->method('load')
            ->willThrowException($typeError);

        $dataLoader = new PaymentMethodDataLoader($paymentMethodRoute);

        try {
            $dataLoader->load(
                new LoaderInputs(['associations' => [], 'onlyAvailable' => true]),
                self::requirement(),
                $context,
                new Request(),
            );

            static::fail('Expected the TypeError to propagate out of load() instead of degrading to notFound');
        } catch (\TypeError $caught) {
            static::assertSame($typeError, $caught);
        }
    }

    /**
     * Sample domain exceptions, not one row per catch arm: the loader catches the single covering ancestor
     * `ShopwareHttpException`, so no row maps to a clause of its own.
     *
     * @return iterable<string, array{\Throwable}>
     */
    public static function sampleDomainExceptionProvider(): iterable
    {
        // PaymentMethodRoute::load() executes the PaymentMethodRouteHook store-api route hook through
        // ScriptExecutor, which rewraps every Throwable an app script raises into
        // ScriptExecutionFailedException, so no enumeration of the chain's own exception classes can cover it.
        yield 'app script failure rewrapped as ScriptExecutionFailedException' => [
            ScriptException::scriptExecutionFailed('payment-method-route', 'payment-method-route.twig', new \RuntimeException('app script failed')),
        ];

        // Not a reachability claim: this row pins the clause to the ancestor rather than to the chain's own
        // classes. DecorationPatternException extends ShopwareHttpException directly instead of through
        // HttpException, so a clause narrowed to one branch of that line would let it escape.
        yield 'a class outside the chain that extends ShopwareHttpException directly' => [
            new DecorationPatternException(AbstractPaymentMethodRoute::class),
        ];
    }

    private static function requirement(): DataRequirement
    {
        return new DataRequirement('paymentMethodKey', 'payment_method', new PaymentMethodLoaderConfig());
    }
}
