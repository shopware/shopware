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
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PostWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopware\Core\Framework\Webhook\Authorization\Ownership\OwnerType;
use Shopware\Core\Framework\Webhook\Authorization\Ownership\WriteAuthorizer;
use Shopware\Core\Framework\Webhook\Authorization\RestrictWriteSubscriber;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\Subscriber;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\SubscriptionRefusals;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\SubscriptionValidator;
use Shopware\Core\Framework\Webhook\Service\WebhookLoader;
use Shopware\Core\Framework\Webhook\Webhook;
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

    private WebhookLoader $loader;

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

        $this->loader = static::createStub(WebhookLoader::class);
    }

    public function testGetSubscribedEvents(): void
    {
        static::assertSame([
            PreWriteValidationEvent::class => 'restrictModification',
            PostWriteValidationEvent::class => 'restrictSubscription',
        ], RestrictWriteSubscriber::getSubscribedEvents());
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
        $event = $this->createPreEvent([
            new UpdateCommand($webhook, ['id' => $update], ['id' => $update], $existence, '/update'),
            new DeleteCommand($webhook, ['id' => $delete], $existence),
        ]);

        (new RestrictWriteSubscriber($authorizer, $this->validator, $this->loader))->restrictModification($event);

        static::assertSame([$violation], $event->getExceptions()->getExceptions());
    }

    public function testOnlyWebhookUpdatesAndDeletesAreAuthorized(): void
    {
        $authorizer = $this->createMock(WriteAuthorizer::class);
        $authorizer->expects($this->never())->method('getModificationViolations');

        $id = Uuid::randomBytes();
        $webhook = $this->registry->getByEntityName(WebhookDefinition::ENTITY_NAME);
        $existence = static::createStub(EntityExistence::class);

        (new RestrictWriteSubscriber($authorizer, $this->validator, $this->loader))->restrictModification($this->createPreEvent([
            new InsertCommand($webhook, ['id' => $id], ['id' => $id], $existence, '/insert'),
            new CascadeDeleteCommand($webhook, ['id' => $id], $existence),
            new UpdateCommand($this->registry->getByEntityName('tax'), ['id' => $id], ['id' => $id], $existence, '/update'),
        ]));
    }

    public function testOnlyEventAndAclRoleChangesAreChecked(): void
    {
        $loader = $this->createMock(WebhookLoader::class);
        $loader->expects($this->never())->method('getWebhooksByIds');

        $event = $this->createPostEvent([$this->updateWebhook(['url' => 'https://example.com'])]);

        (new RestrictWriteSubscriber($this->authorizer, $this->validator, $loader))->restrictSubscription($event);
    }

    public function testRefusalsRejectTheWrite(): void
    {
        $validator = static::createStub(SubscriptionValidator::class);
        $validator->method('validate')->willReturn(new SubscriptionRefusals(
            notHookable: ['product.written'],
            notPermitted: ['order.written'],
            missingPrivileges: ['customer.written' => ['customer:read']],
        ));

        $insert = $this->insertWebhook();

        $loader = static::createStub(WebhookLoader::class);
        $loader->method('getWebhooksByIds')->willReturn([$this->webhook($insert)]);

        $event = $this->createPostEvent([$insert]);

        (new RestrictWriteSubscriber($this->authorizer, $validator, $loader))->restrictSubscription($event);

        $exceptions = $event->getExceptions()->getExceptions();
        static::assertCount(3, $exceptions);
        static::assertSame(WebhookException::webhookEventNotPermitted('product.written')->getMessage(), $exceptions[0]->getMessage());
        static::assertSame(WebhookException::webhookEventNotPermitted('order.written')->getMessage(), $exceptions[1]->getMessage());
        static::assertSame(WebhookException::webhookEventPrivilegesMissing('customer.written', ['customer:read'])->getMessage(), $exceptions[2]->getMessage());
    }

    public function testPrivilegesComeFromTheWebhookRoles(): void
    {
        $insert = $this->insertWebhook();

        $loader = static::createStub(WebhookLoader::class);
        $loader->method('getWebhooksByIds')->willReturn([$this->webhook($insert, ownerRoleIds: ['role-a', 'role-b'])]);
        $loader->method('getPrivilegesForRoles')->willReturn([
            'role-a' => new AclPrivilegeCollection(['product:read', 'order:read']),
            'role-b' => new AclPrivilegeCollection(['order:read', 'customer:read']),
        ]);

        $validator = $this->createMock(SubscriptionValidator::class);
        $validator->expects($this->once())
            ->method('validate')
            ->with(['product.written' => 'product.written'], ['product:read', 'order:read', 'customer:read'], Subscriber::user())
            ->willReturn(new SubscriptionRefusals());

        (new RestrictWriteSubscriber($this->authorizer, $validator, $loader))->restrictSubscription($this->createPostEvent([$insert]));
    }

    public function testPrivilegesAreNotCheckedForAnAdminOwner(): void
    {
        $insert = $this->insertWebhook();

        $loader = static::createStub(WebhookLoader::class);
        $loader->method('getWebhooksByIds')->willReturn([$this->webhook($insert, ownerType: OwnerType::Admin, ownerRoleIds: [])]);

        $validator = $this->createMock(SubscriptionValidator::class);
        $validator->expects($this->once())
            ->method('validate')
            ->with(['product.written' => 'product.written'], null)
            ->willReturn(new SubscriptionRefusals());

        (new RestrictWriteSubscriber($this->authorizer, $validator, $loader))->restrictSubscription($this->createPostEvent([$insert]));
    }

    public function testAnAclRoleChangeIsCheckedEvenForAnAdmin(): void
    {
        $update = $this->updateWebhook(['acl_role_ids' => json_encode(['role'], \JSON_THROW_ON_ERROR)]);

        $loader = static::createStub(WebhookLoader::class);
        $loader->method('getWebhooksByIds')->willReturn([$this->webhook($update, eventName: 'order.written')]);
        $loader->method('getPrivilegesForRoles')->willReturn(['role' => new AclPrivilegeCollection(['order:read'])]);

        $validator = $this->createMock(SubscriptionValidator::class);
        $validator->expects($this->once())
            ->method('validate')
            ->with(['order.written' => 'order.written'], ['order:read'], Subscriber::admin())
            ->willReturn(new SubscriptionRefusals());

        $admin = new AdminApiSource(Uuid::randomHex());
        $admin->setIsAdmin(true);

        (new RestrictWriteSubscriber($this->authorizer, $validator, $loader))->restrictSubscription($this->createPostEvent([$update], $admin));
    }

    public function testAclRolesAreOnlyCheckedWhenWritten(): void
    {
        $update = $this->updateWebhook(['event_name' => 'product.written']);

        $loader = static::createStub(WebhookLoader::class);
        $loader->method('getWebhooksByIds')->willReturn([$this->webhook($update, aclRoleIds: ['role', 'lost-role'])]);

        $event = $this->createPostEvent([$update]);

        (new RestrictWriteSubscriber($this->authorizer, $this->validator, $loader))->restrictSubscription($event);

        static::assertSame([], $event->getExceptions()->getExceptions());
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
     * @param array<string, mixed> $payload
     */
    private function updateWebhook(array $payload): UpdateCommand
    {
        return new UpdateCommand(
            $this->registry->getByEntityName(WebhookDefinition::ENTITY_NAME),
            $payload,
            ['id' => Uuid::randomBytes()],
            static::createStub(EntityExistence::class),
            '/update'
        );
    }

    /**
     * @param list<string> $ownerRoleIds
     * @param list<string>|null $aclRoleIds
     */
    private function webhook(
        WriteCommand $command,
        string $eventName = 'product.written',
        OwnerType $ownerType = OwnerType::Restricted,
        array $ownerRoleIds = ['role'],
        ?array $aclRoleIds = null,
    ): Webhook {
        return new Webhook(
            id: Uuid::fromBytesToHex($command->getPrimaryKey()['id']),
            webhookName: 'webhook',
            eventName: $eventName,
            url: 'https://example.com',
            onlyLiveVersion: false,
            appId: null,
            appName: null,
            appSourceType: null,
            appActive: false,
            appVersion: null,
            appSecret: null,
            ownerType: $ownerType,
            ownerRoleIds: $ownerRoleIds,
            aclRoleIds: $aclRoleIds,
        );
    }

    /**
     * @param list<WriteCommand> $commands
     */
    private function createPreEvent(array $commands): PreWriteValidationEvent
    {
        return new PreWriteValidationEvent(WriteContext::createFromContext(new Context(new AdminApiSource(null))), $commands);
    }

    /**
     * @param list<WriteCommand> $commands
     */
    private function createPostEvent(array $commands, ContextSource $source = new AdminApiSource(null)): PostWriteValidationEvent
    {
        return new PostWriteValidationEvent(WriteContext::createFromContext(new Context($source)), $commands);
    }
}
