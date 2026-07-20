<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\RevocationRequest\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Service\CmsFormSlotConfigResolver;
use Shopware\Core\Content\RevocationRequest\SalesChannel\RevocationRequestRoute;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Generator;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(RevocationRequestRoute::class)]
class RevocationRequestRouteTest extends TestCase
{
    /**
     * @param array<string, string> $data
     */
    #[DataProvider('validationDataProvider')]
    public function testRequestValidatesFormData(array $data): void
    {
        $requestData = new RequestDataBag($data);
        $definition = new DataValidationDefinition('revocation_request_form.create');

        $validationFactory = static::createStub(DataValidationFactoryInterface::class);
        $validationFactory->method('create')->willReturn($definition);

        $validator = $this->createMock(DataValidator::class);
        $validator->expects($this->once())
            ->method('getViolations')
            ->willReturnCallback(static function (array $validatedData, DataValidationDefinition $validatedDefinition) use ($data, $definition): ConstraintViolationList {
                foreach ($data as $property => $value) {
                    static::assertSame($value, $validatedData[$property] ?? null);
                }
                static::assertSame($definition, $validatedDefinition);

                return new ConstraintViolationList();
            });

        $route = $this->createRevocationRequestRoute(
            validatorFactory: $validationFactory,
            validator: $validator,
        );

        $route->request($requestData, $this->createSalesChannelContext());
    }

    public static function validationDataProvider(): \Generator
    {
        yield 'valid form data' => [[
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'email' => 'max@muster.com',
            'contractNumber' => 'SW123456789',
            'comment' => 'This is a simple comment',
        ]];

        yield 'form data with optional context fields' => [[
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'email' => 'max@muster.com',
            'contractNumber' => 'SW123456789',
            'comment' => 'This is a simple comment',
            'slotId' => Uuid::randomHex(),
            'navigationId' => Uuid::randomHex(),
            'entityName' => 'landing_page',
        ]];
    }

    private function createRequestStackMock(): RequestStack
    {
        $requestStackMock = static::createStub(RequestStack::class);
        $requestStackMock->method('getMainRequest')->willReturn(new Request());

        return $requestStackMock;
    }

    private function createRevocationRequestRoute(
        ?EventDispatcherInterface $eventDispatcher = null,
        ?DataValidationFactoryInterface $validatorFactory = null,
        ?DataValidator $validator = null,
    ): RevocationRequestRoute {
        $validatorFactory ??= static::createStub(DataValidationFactoryInterface::class);
        $validator ??= static::createStub(DataValidator::class);

        $slotConfigResolver = static::createStub(CmsFormSlotConfigResolver::class);
        $slotConfigResolver->method('resolve')->willReturn([
            'receivers' => ['foo' => 'bar'],
            'message' => 'baz',
        ]);

        return new RevocationRequestRoute(
            $validatorFactory,
            $validator,
            $this->createRequestStackMock(),
            static::createStub(RateLimiter::class),
            $eventDispatcher ?? static::createStub(EventDispatcherInterface::class),
            new NativeClock(),
            $slotConfigResolver,
        );
    }

    private function createSalesChannelContext(): SalesChannelContext
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());

        return Generator::generateSalesChannelContext(
            baseContext: new Context(new SalesChannelApiSource(Uuid::randomHex())),
            salesChannel: $salesChannel
        );
    }
}
