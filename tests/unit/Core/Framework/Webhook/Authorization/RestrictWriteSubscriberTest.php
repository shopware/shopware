<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Authorization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Acl\AclRoleLoader;
use Shopware\Core\Framework\Api\Acl\AclRoles;
use Shopware\Core\Framework\Api\Acl\ApiCallerType;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
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
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopware\Core\Framework\Webhook\Authorization\Ownership\WriteAuthorizer;
use Shopware\Core\Framework\Webhook\Authorization\RestrictWriteSubscriber;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\Subscriber;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\SubscriberType;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\SubscriptionRefusals;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\SubscriptionValidator;
use Shopware\Core\Framework\Webhook\Authorization\WebhookSubscription;
use Shopware\Core\Framework\Webhook\Service\WebhookLoader;
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
    private const USER_ID = 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6';

    private StaticDefinitionInstanceRegistry $registry;

    private WriteAuthorizer $authorizer;

    private SubscriptionValidator $validator;

    private WebhookLoader $webhookLoader;

    private AclRoleLoader $aclRoleLoader;

    protected function setUp(): void
    {
        $this->registry = new StaticDefinitionInstanceRegistry(
            [WebhookDefinition::class, TaxDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $this->authorizer = static::createStub(WriteAuthorizer::class);
        $this->authorizer->method('getModificationViolations')->willReturn([]);

        $this->validator = static::createStub(SubscriptionValidator::class);
        $this->validator->method('validate')->willReturn(new SubscriptionRefusals());

        $this->webhookLoader = static::createStub(WebhookLoader::class);
        $this->webhookLoader->method('getSubscriptions')->willReturn([]);

        $this->aclRoleLoader = static::createStub(AclRoleLoader::class);
        $this->aclRoleLoader->method('getRoles')->willReturn([self::USER_ID => new AclRoles(false, ['role'])]);
        $this->aclRoleLoader->method('getPrivilegesForRoles')->willReturn([]);
    }

    public function testGetSubscribedEvents(): void
    {
        static::assertSame([PreWriteValidationEvent::class => 'validate'], RestrictWriteSubscriber::getSubscribedEvents());
    }

    public function testModificationViolationsRejectTheWrite(): void
    {
        $update = $this->updateWebhook(['url' => 'https://example.com']);
        $delete = new DeleteCommand($this->webhookDefinition(), ['id' => Uuid::randomBytes()], static::createStub(EntityExistence::class));
        $violation = WebhookException::appWebhookNotModifiable(self::id($update));

        $this->authorizer = $this->createMock(WriteAuthorizer::class);
        $this->authorizer->expects($this->once())
            ->method('getModificationViolations')
            ->with([self::id($update), self::id($delete)])
            ->willReturn([self::id($update) => $violation]);

        $event = $this->createEvent([$update, $delete]);
        $this->createSubscriber()->validate($event);

        static::assertSame([$violation], $event->getExceptions()->getExceptions());
    }

    public function testInsertsCascadeDeletesAndOtherEntitiesAreNotAuthorized(): void
    {
        $this->authorizer = $this->createMock(WriteAuthorizer::class);
        $this->authorizer->expects($this->once())->method('getModificationViolations')->with([])->willReturn([]);

        $id = Uuid::randomBytes();
        $existence = static::createStub(EntityExistence::class);

        $this->createSubscriber()->validate($this->createEvent([
            new InsertCommand($this->webhookDefinition(), ['id' => $id], ['id' => $id], $existence, '/insert'),
            new CascadeDeleteCommand($this->webhookDefinition(), ['id' => Uuid::randomBytes()], $existence),
            new UpdateCommand($this->registry->getByEntityName('tax'), ['id' => $id], ['id' => $id], $existence, '/update'),
        ]));
    }

    public function testARejectedModificationIsNotCheckedFurther(): void
    {
        $update = $this->updateWebhook(['event_name' => 'product.written']);

        $this->authorizer = static::createStub(WriteAuthorizer::class);
        $this->authorizer->method('getModificationViolations')
            ->willReturn([self::id($update) => WebhookException::webhookNotOwned(self::id($update))]);

        $this->validator = $this->createMock(SubscriptionValidator::class);
        $this->validator->expects($this->never())->method('validate');

        $this->createSubscriber()->validate($this->createEvent([$update]));
    }

    public function testAnEmptyAclRoleListIsRejected(): void
    {
        $event = $this->createEvent([$this->insertWebhook(aclRoleIds: [])]);
        $this->createSubscriber()->validate($event);

        $this->assertAclRoleIdsViolation($event, 'aclRoleIds must not be empty. Leave it out to use all of the owner\'s roles.');
    }

    public function testEmptyAclRoleEntriesAreRejected(): void
    {
        $event = $this->createEvent([$this->insertWebhook(aclRoleIds: ['role', '', '0'])]);
        $this->createSubscriber()->validate($event);

        $this->assertAclRoleIdsViolation($event, 'aclRoleIds must not contain empty entries.');
    }

    public function testOnlyEventAndAclRoleChangesAreChecked(): void
    {
        $this->validator = $this->createMock(SubscriptionValidator::class);
        $this->validator->expects($this->never())->method('validate');

        $this->createSubscriber()->validate($this->createEvent([$this->updateWebhook(['url' => 'https://example.com'])]));
    }

    public function testSystemWritesAreNotChecked(): void
    {
        $this->validator = $this->createMock(SubscriptionValidator::class);
        $this->validator->expects($this->never())->method('validate');

        $this->createSubscriber()->validate($this->createEvent([$this->insertWebhook()], Context::createDefaultContext()));
    }

    public function testRefusalsRejectTheWrite(): void
    {
        $product = $this->insertWebhook('product.written');
        $order = $this->insertWebhook('order.written');
        $customer = $this->insertWebhook('customer.written');

        $this->validator = static::createStub(SubscriptionValidator::class);
        $this->validator->method('validate')->willReturn(new SubscriptionRefusals(
            notHookable: [self::id($product)],
            notPermitted: [self::id($order)],
            missingPrivileges: [self::id($customer) => ['customer:read']],
        ));

        $event = $this->createEvent([$product, $order, $customer]);
        $this->createSubscriber()->validate($event);

        $exceptions = $event->getExceptions()->getExceptions();
        static::assertCount(3, $exceptions);
        static::assertSame(WebhookException::webhookEventNotPermitted('product.written')->getMessage(), $exceptions[0]->getMessage());
        static::assertSame(WebhookException::webhookEventNotPermitted('order.written')->getMessage(), $exceptions[1]->getMessage());
        static::assertSame(WebhookException::webhookEventPrivilegesMissing('customer.written', ['customer:read'])->getMessage(), $exceptions[2]->getMessage());
    }

    public function testAnInsertIsCheckedWithThePrivilegesOfTheCallersRoles(): void
    {
        $insert = $this->insertWebhook();

        $this->aclRoleLoader = $this->createMock(AclRoleLoader::class);
        $this->aclRoleLoader->expects($this->once())
            ->method('getRoles')
            ->with([self::USER_ID])
            ->willReturn([self::USER_ID => new AclRoles(false, ['role-a', 'role-b'])]);
        $this->aclRoleLoader->method('getPrivilegesForRoles')->willReturn([
            'role-a' => new AclPrivilegeCollection(['product:read', 'order:read']),
            'role-b' => new AclPrivilegeCollection(['order:read', 'customer:read']),
        ]);

        $this->validator = $this->createMock(SubscriptionValidator::class);
        $this->validator->expects($this->once())
            ->method('validate')
            ->with(
                [self::id($insert) => 'product.written'],
                [self::id($insert) => ['product:read', 'order:read', 'customer:read']],
                Subscriber::api(ApiCallerType::User),
            )
            ->willReturn(new SubscriptionRefusals());

        $this->createSubscriber()->validate($this->createEvent([$insert]));
    }

    public function testAnUnrestrictedOwnerHoldsEveryPrivilege(): void
    {
        $insert = $this->insertWebhook();

        $this->aclRoleLoader = static::createStub(AclRoleLoader::class);
        $this->aclRoleLoader->method('getRoles')->willReturn([self::USER_ID => new AclRoles(true, [])]);
        $this->aclRoleLoader->method('getPrivilegesForRoles')->willReturn([]);

        $this->validator = $this->createMock(SubscriptionValidator::class);
        $this->validator->expects($this->once())
            ->method('validate')
            ->with([self::id($insert) => 'product.written'], [self::id($insert) => null])
            ->willReturn(new SubscriptionRefusals());

        $this->createSubscriber()->validate($this->createEvent([$insert]));
    }

    public function testAnUpdateIsCheckedWithTheStoredOwnersRolesAndTheNewEvent(): void
    {
        $update = $this->updateWebhook(['event_name' => 'order.written']);
        $ownerId = Uuid::randomHex();

        $this->webhookLoader = static::createStub(WebhookLoader::class);
        $this->webhookLoader->method('getSubscriptions')->willReturn([
            self::id($update) => new WebhookSubscription(self::id($update), SubscriberType::User, $ownerId, 'product.written', null),
        ]);

        $this->aclRoleLoader = $this->createMock(AclRoleLoader::class);
        $this->aclRoleLoader->expects($this->once())
            ->method('getRoles')
            ->with([$ownerId])
            ->willReturn([$ownerId => new AclRoles(false, ['role'])]);
        $this->aclRoleLoader->method('getPrivilegesForRoles')->willReturn(['role' => new AclPrivilegeCollection(['order:read'])]);

        $this->validator = $this->createMock(SubscriptionValidator::class);
        $this->validator->expects($this->once())
            ->method('validate')
            ->with([self::id($update) => 'order.written'], [self::id($update) => ['order:read']])
            ->willReturn(new SubscriptionRefusals());

        $this->createSubscriber()->validate($this->createEvent([$update]));
    }

    public function testAnUpdateOfAnAppsWebhookIsLeftToTheModificationCheck(): void
    {
        $update = $this->updateWebhook(['event_name' => 'order.written']);

        $this->webhookLoader = static::createStub(WebhookLoader::class);
        $this->webhookLoader->method('getSubscriptions')->willReturn([
            self::id($update) => new WebhookSubscription(self::id($update), SubscriberType::App, Uuid::randomHex(), 'product.written', null),
        ]);

        $this->validator = $this->createMock(SubscriptionValidator::class);
        $this->validator->expects($this->once())->method('validate')->with([], [])->willReturn(new SubscriptionRefusals());

        $this->createSubscriber()->validate($this->createEvent([$update]));
    }

    public function testAclRoleIdsLimitTheOwnersRoles(): void
    {
        $insert = $this->insertWebhook(aclRoleIds: ['role-a']);

        $this->aclRoleLoader = static::createStub(AclRoleLoader::class);
        $this->aclRoleLoader->method('getRoles')->willReturn([self::USER_ID => new AclRoles(false, ['role-a', 'role-b'])]);
        $this->aclRoleLoader->method('getPrivilegesForRoles')->willReturn([
            'role-a' => new AclPrivilegeCollection(['product:read']),
            'role-b' => new AclPrivilegeCollection(['order:read']),
        ]);

        $this->validator = $this->createMock(SubscriptionValidator::class);
        $this->validator->expects($this->once())
            ->method('validate')
            ->with([self::id($insert) => 'product.written'], [self::id($insert) => ['product:read']])
            ->willReturn(new SubscriptionRefusals());

        $this->createSubscriber()->validate($this->createEvent([$insert]));
    }

    public function testAclRolesTheOwnerDoesNotHoldAreRejected(): void
    {
        $event = $this->createEvent([$this->insertWebhook(aclRoleIds: ['role', 'other-role'])]);
        $this->createSubscriber()->validate($event);

        $exceptions = $event->getExceptions()->getExceptions();
        static::assertCount(1, $exceptions);
        static::assertInstanceOf(WriteConstraintViolationException::class, $exceptions[0]);

        $violations = $exceptions[0]->getViolations();
        static::assertCount(1, $violations);
        static::assertSame('/aclRoleIds', $violations->get(0)->getPropertyPath());
        static::assertSame('Role "other-role" is not held by the webhook\'s owner.', $violations->get(0)->getMessage());
    }

    public function testAnUnrestrictedOwnerMayListAnyRole(): void
    {
        $this->aclRoleLoader = static::createStub(AclRoleLoader::class);
        $this->aclRoleLoader->method('getRoles')->willReturn([self::USER_ID => new AclRoles(true, [])]);
        $this->aclRoleLoader->method('getPrivilegesForRoles')->willReturn([]);

        $event = $this->createEvent([$this->insertWebhook(aclRoleIds: ['any-role'])]);
        $this->createSubscriber()->validate($event);

        static::assertSame([], $event->getExceptions()->getExceptions());
    }

    public function testStoredAclRolesAreOnlyRejectedWhenWritten(): void
    {
        $update = $this->updateWebhook(['event_name' => 'product.written']);

        $this->webhookLoader = static::createStub(WebhookLoader::class);
        $this->webhookLoader->method('getSubscriptions')->willReturn([
            self::id($update) => new WebhookSubscription(self::id($update), SubscriberType::User, self::USER_ID, 'order.written', ['role', 'lost-role']),
        ]);

        $event = $this->createEvent([$update]);
        $this->createSubscriber()->validate($event);

        static::assertSame([], $event->getExceptions()->getExceptions());
    }

    private function createSubscriber(): RestrictWriteSubscriber
    {
        return new RestrictWriteSubscriber($this->authorizer, $this->validator, $this->webhookLoader, $this->aclRoleLoader);
    }

    private function webhookDefinition(): WebhookDefinition
    {
        $definition = $this->registry->getByEntityName(WebhookDefinition::ENTITY_NAME);
        static::assertInstanceOf(WebhookDefinition::class, $definition);

        return $definition;
    }

    /**
     * @param list<string>|null $aclRoleIds
     */
    private function insertWebhook(string $eventName = 'product.written', ?array $aclRoleIds = null): InsertCommand
    {
        $id = Uuid::randomBytes();
        $payload = ['id' => $id, 'event_name' => $eventName];
        if ($aclRoleIds !== null) {
            $payload['acl_role_ids'] = json_encode($aclRoleIds, \JSON_THROW_ON_ERROR);
        }

        return new InsertCommand($this->webhookDefinition(), $payload, ['id' => $id], static::createStub(EntityExistence::class), '/insert');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function updateWebhook(array $payload): UpdateCommand
    {
        return new UpdateCommand(
            $this->webhookDefinition(),
            $payload,
            ['id' => Uuid::randomBytes()],
            static::createStub(EntityExistence::class),
            '/update'
        );
    }

    private static function id(WriteCommand $command): string
    {
        return Uuid::fromBytesToHex($command->getPrimaryKey()['id']);
    }

    /**
     * @param list<WriteCommand> $commands
     */
    private function createEvent(array $commands, ?Context $context = null): PreWriteValidationEvent
    {
        $context ??= new Context(new AdminApiSource(self::USER_ID));

        return new PreWriteValidationEvent(WriteContext::createFromContext($context), $commands);
    }

    private function assertAclRoleIdsViolation(PreWriteValidationEvent $event, string $message): void
    {
        $exceptions = $event->getExceptions()->getExceptions();
        static::assertCount(1, $exceptions);
        static::assertInstanceOf(WriteConstraintViolationException::class, $exceptions[0]);

        $violations = $exceptions[0]->getViolations();
        static::assertCount(1, $violations);
        static::assertSame('/aclRoleIds', $violations->get(0)->getPropertyPath());
        static::assertSame($message, $violations->get(0)->getMessage());
    }
}
