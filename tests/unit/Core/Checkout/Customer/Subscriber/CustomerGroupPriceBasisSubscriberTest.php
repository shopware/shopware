<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\Subscriber\CustomerGroupPriceBasisSubscriber;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 *
 * @deprecated tag:v6.8.0 - Will be removed together with the CustomerGroupPriceBasisSubscriber.
 */
#[Package('discovery')]
#[CoversClass(CustomerGroupPriceBasisSubscriber::class)]
class CustomerGroupPriceBasisSubscriberTest extends TestCase
{
    private CustomerGroupDefinition $definition;

    protected function setUp(): void
    {
        new StaticDefinitionInstanceRegistry(
            [$this->definition = new CustomerGroupDefinition()],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
    }

    public function testSubscribesToTheWriteEvent(): void
    {
        static::assertSame(
            [EntityWriteEvent::class => 'derivePriceBasis'],
            CustomerGroupPriceBasisSubscriber::getSubscribedEvents()
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('insertProvider')]
    public function testInsertGetsTheBasisMatchingItsDisplayMode(array $payload, string $expectedBasis): void
    {
        $command = new InsertCommand(
            $this->definition,
            $payload,
            ['id' => Uuid::randomBytes()],
            static::createStub(EntityExistence::class),
            '/0'
        );

        $this->dispatch($command);

        static::assertSame($expectedBasis, $command->getPayload()['price_basis']);
    }

    public static function insertProvider(): \Generator
    {
        yield 'net display without a basis' => [['display_gross' => 0], CustomerGroupEntity::PRICE_BASIS_NET];

        yield 'gross display without a basis' => [['display_gross' => 1], CustomerGroupEntity::PRICE_BASIS_GROSS];

        yield 'neither field falls back to the gross display default' => [[], CustomerGroupEntity::PRICE_BASIS_GROSS];

        yield 'an explicit null basis is derived as well' => [
            ['display_gross' => 0, 'price_basis' => null],
            CustomerGroupEntity::PRICE_BASIS_NET,
        ];

        yield 'an explicit basis is kept' => [
            ['display_gross' => 0, 'price_basis' => CustomerGroupEntity::PRICE_BASIS_GROSS],
            CustomerGroupEntity::PRICE_BASIS_GROSS,
        ];
    }

    public function testUpdatesStayUntouched(): void
    {
        $command = new UpdateCommand(
            $this->definition,
            ['display_gross' => 0],
            ['id' => Uuid::randomBytes()],
            static::createStub(EntityExistence::class),
            '/0'
        );

        $this->dispatch($command);

        static::assertSame(['display_gross' => 0], $command->getPayload());
    }

    private function dispatch(WriteCommand $command): void
    {
        (new CustomerGroupPriceBasisSubscriber())->derivePriceBasis(
            EntityWriteEvent::create(WriteContext::createFromContext(Context::createDefaultContext()), [$command])
        );
    }
}
