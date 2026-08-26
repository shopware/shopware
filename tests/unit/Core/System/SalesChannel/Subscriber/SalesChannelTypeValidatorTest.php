<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelType\SalesChannelTypeDefinition;
use Shopware\Core\System\SalesChannel\Exception\DefaultSalesChannelTypeCannotBeDeleted;
use Shopware\Core\System\SalesChannel\Subscriber\SalesChannelTypeValidator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SalesChannelTypeValidator::class)]
class SalesChannelTypeValidatorTest extends TestCase
{
    public function testOnlyProtectedSalesChannelTypeDeletionIsRejected(): void
    {
        $definitionRegistry = new StaticDefinitionInstanceRegistry(
            [SalesChannelTypeDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        );
        $definition = $definitionRegistry->get(SalesChannelTypeDefinition::class);
        $existence = EntityExistence::createEmpty();
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new DeleteCommand($definition, ['id' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_STOREFRONT)], $existence),
                new DeleteCommand($definition, ['id' => Uuid::randomBytes()], $existence),
                new InsertCommand($definition, [], ['id' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_API)], $existence, '/sales-channel-type'),
            ],
        );

        (new SalesChannelTypeValidator())->preWriteValidateEvent($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());
        static::assertInstanceOf(DefaultSalesChannelTypeCannotBeDeleted::class, $event->getExceptions()->getExceptions()[0]);
    }
}
