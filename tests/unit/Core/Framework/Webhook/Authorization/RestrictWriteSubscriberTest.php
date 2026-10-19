<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Authorization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\CascadeDeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Authorization\Ownership\WriteAuthorizer;
use Shopware\Core\Framework\Webhook\Authorization\RestrictWriteSubscriber;
use Shopware\Core\Framework\Webhook\WebhookDefinition;
use Shopware\Core\Framework\Webhook\WebhookException;
use Shopware\Core\System\Tax\TaxDefinition;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RestrictWriteSubscriber::class)]
class RestrictWriteSubscriberTest extends TestCase
{
    private StaticDefinitionInstanceRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new StaticDefinitionInstanceRegistry(
            [WebhookDefinition::class, TaxDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
    }

    public function testGetSubscribedEvents(): void
    {
        static::assertSame([PreWriteValidationEvent::class => 'restrictWrite'], RestrictWriteSubscriber::getSubscribedEvents());
    }

    public function testAuthorizerViolationsRejectTheWrite(): void
    {
        $update = Uuid::randomBytes();
        $delete = Uuid::randomBytes();
        $violation = WebhookException::appWebhookNotModifiable(Uuid::fromBytesToHex($update));

        $authorizer = $this->createMock(WriteAuthorizer::class);
        $authorizer->expects($this->once())
            ->method('getModificationViolations')
            ->with([Uuid::fromBytesToHex($update), Uuid::fromBytesToHex($delete)])
            ->willReturn([$violation]);

        $webhook = $this->registry->getByEntityName(WebhookDefinition::ENTITY_NAME);
        $existence = static::createStub(EntityExistence::class);
        $event = new PreWriteValidationEvent(WriteContext::createFromContext(Context::createDefaultContext()), [
            new UpdateCommand($webhook, ['id' => $update], ['id' => $update], $existence, '/update'),
            new DeleteCommand($webhook, ['id' => $delete], $existence),
        ]);

        (new RestrictWriteSubscriber($authorizer))->restrictWrite($event);

        static::assertSame([$violation], $event->getExceptions()->getExceptions());
    }

    public function testOnlyWebhookUpdatesAndDeletesAreAuthorized(): void
    {
        $authorizer = $this->createMock(WriteAuthorizer::class);
        $authorizer->expects($this->never())->method('getModificationViolations');

        $id = Uuid::randomBytes();
        $webhook = $this->registry->getByEntityName(WebhookDefinition::ENTITY_NAME);
        $existence = static::createStub(EntityExistence::class);

        (new RestrictWriteSubscriber($authorizer))->restrictWrite(new PreWriteValidationEvent(WriteContext::createFromContext(Context::createDefaultContext()), [
            new InsertCommand($webhook, ['id' => $id], ['id' => $id], $existence, '/insert'),
            new CascadeDeleteCommand($webhook, ['id' => $id], $existence),
            new UpdateCommand($this->registry->getByEntityName('tax'), ['id' => $id], ['id' => $id], $existence, '/update'),
        ]));
    }
}
