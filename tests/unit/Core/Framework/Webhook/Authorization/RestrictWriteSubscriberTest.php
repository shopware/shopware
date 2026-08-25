<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Authorization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\ContextSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\CascadeDeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Authorization\Ownership\WriteAuthorizer;
use Shopware\Core\Framework\Webhook\Authorization\RestrictWriteSubscriber;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\SubscriptionRefusals;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\SubscriptionValidator;
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

    private WriteAuthorizer $authorizer;

    private SubscriptionValidator $validator;

    protected function setUp(): void
    {
        $this->registry = new StaticDefinitionInstanceRegistry(
            [WebhookDefinition::class, TaxDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $this->authorizer = static::createStub(WriteAuthorizer::class);

        $this->validator = static::createStub(SubscriptionValidator::class);
        $this->validator->method('validate')->willReturn(new SubscriptionRefusals());
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
        $event = $this->createEvent([
            new UpdateCommand($webhook, ['id' => $update], ['id' => $update], $existence, '/update'),
            new DeleteCommand($webhook, ['id' => $delete], $existence),
        ]);

        (new RestrictWriteSubscriber($authorizer, $this->validator))->restrictWrite($event);

        static::assertSame([$violation], $event->getExceptions()->getExceptions());
    }

    public function testOnlyWebhookUpdatesAndDeletesAreAuthorized(): void
    {
        $authorizer = $this->createMock(WriteAuthorizer::class);
        $authorizer->expects($this->never())->method('getModificationViolations');

        $id = Uuid::randomBytes();
        $webhook = $this->registry->getByEntityName(WebhookDefinition::ENTITY_NAME);
        $existence = static::createStub(EntityExistence::class);

        (new RestrictWriteSubscriber($authorizer, $this->validator))->restrictWrite($this->createEvent([
            new InsertCommand($webhook, ['id' => $id], ['id' => $id], $existence, '/insert'),
            new CascadeDeleteCommand($webhook, ['id' => $id], $existence),
            new UpdateCommand($this->registry->getByEntityName('tax'), ['id' => $id], ['id' => $id], $existence, '/update'),
        ]));
    }

    public function testRefusalsRejectTheWrite(): void
    {
        $validator = static::createStub(SubscriptionValidator::class);
        $validator->method('validate')->willReturn(new SubscriptionRefusals(
            notHookable: ['product.written'],
            notPermitted: ['order.written'],
            missingPrivileges: ['customer.written' => ['customer:read']],
        ));

        $event = $this->createEvent([$this->insertWebhook()]);

        (new RestrictWriteSubscriber($this->authorizer, $validator))->restrictWrite($event);

        $exceptions = $event->getExceptions()->getExceptions();
        static::assertCount(3, $exceptions);
        static::assertSame(WebhookException::webhookEventNotPermitted('product.written')->getMessage(), $exceptions[0]->getMessage());
        static::assertSame(WebhookException::webhookEventNotPermitted('order.written')->getMessage(), $exceptions[1]->getMessage());
        static::assertSame(WebhookException::webhookEventPrivilegesMissing('customer.written', ['customer:read'])->getMessage(), $exceptions[2]->getMessage());
    }

    private function insertWebhook(): InsertCommand
    {
        $id = Uuid::randomBytes();

        return new InsertCommand(
            $this->registry->getByEntityName(WebhookDefinition::ENTITY_NAME),
            ['id' => $id, 'event_name' => 'product.written'],
            ['id' => $id],
            static::createStub(EntityExistence::class),
            '/insert'
        );
    }

    /**
     * @param list<WriteCommand> $commands
     */
    private function createEvent(array $commands, ContextSource $source = new AdminApiSource(null)): PreWriteValidationEvent
    {
        return new PreWriteValidationEvent(WriteContext::createFromContext(new Context($source)), $commands);
    }
}
