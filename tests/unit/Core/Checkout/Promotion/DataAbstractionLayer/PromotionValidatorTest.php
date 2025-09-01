<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Promotion\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\DataAbstractionLayer\PromotionValidator;
use Shopware\Core\Checkout\Promotion\PromotionDefinition;
use Shopware\Core\Checkout\Promotion\PromotionException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PromotionValidator::class)]
class PromotionValidatorTest extends TestCase
{
    private StaticDefinitionInstanceRegistry $definitionInstanceRegistry;

    protected function setUp(): void
    {
        $this->definitionInstanceRegistry = new StaticDefinitionInstanceRegistry(
            [PromotionDefinition::class],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );
    }

    public function testGetSubscribedEvents(): void
    {
        static::assertSame(
            [
                PreWriteValidationEvent::class => 'validate',
            ],
            PromotionValidator::getSubscribedEvents()
        );
    }

    public function testValidate(): void
    {
        $promotionId = Uuid::randomBytes();

        $context = Context::createDefaultContext();

        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext($context),
            [new DeleteCommand(
                $this->definitionInstanceRegistry->get(PromotionDefinition::class),
                ['id' => $promotionId],
                new EntityExistence(PromotionDefinition::ENTITY_NAME, ['id' => $promotionId], true, false, false, [])
            )],
        );

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->with(
                'SELECT id FROM promotion WHERE id IN (:ids) AND order_count > 0',
                ['ids' => [$promotionId]],
                ['ids' => ArrayParameterType::BINARY]
            )
            ->willReturn(false);

        $subscriber = new PromotionValidator($connection);
        $subscriber->validate($event);
    }

    public function testValidateWithOrderCount(): void
    {
        $promotionId = Uuid::randomBytes();

        $context = Context::createDefaultContext();

        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext($context),
            [new DeleteCommand(
                $this->definitionInstanceRegistry->get(PromotionDefinition::class),
                ['id' => $promotionId],
                new EntityExistence(PromotionDefinition::ENTITY_NAME, ['id' => $promotionId], true, false, false, [])
            )],
        );

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->with(
                'SELECT id FROM promotion WHERE id IN (:ids) AND order_count > 0',
                ['ids' => [$promotionId]],
                ['ids' => ArrayParameterType::BINARY]
            )
            ->willReturn('someId');

        $this->expectException(PromotionException::class);
        $this->expectExceptionMessage('Promotions cannot be deleted once they have been used in an order.');
        $subscriber = new PromotionValidator($connection);
        $subscriber->validate($event);
    }

    public function testValidateWithoutCommand(): void
    {
        $context = Context::createDefaultContext();

        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext($context),
            []
        );

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())
            ->method('fetchOne');

        $subscriber = new PromotionValidator($connection);
        $subscriber->validate($event);
    }
}
