<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Authorization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Acl\AclRoleLoader;
use Shopware\Core\Framework\Api\Acl\AclRoles;
use Shopware\Core\Framework\Api\Acl\ApiCallerType;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\Subscriber;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\SubscriberType;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\SubscriptionRefusals;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\SubscriptionValidator;
use Shopware\Core\Framework\Webhook\Authorization\WebhookSubscription;
use Shopware\Core\Framework\Webhook\Authorization\WriteAuthorizer;
use Shopware\Core\Framework\Webhook\Service\WebhookLoader;
use Shopware\Core\Framework\Webhook\WebhookDefinition;
use Shopware\Core\Framework\Webhook\WebhookException;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WriteAuthorizer::class)]
class WriteAuthorizerTest extends TestCase
{
    private const USER_ID = 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6';

    private StaticDefinitionInstanceRegistry $registry;

    private SubscriptionValidator $validator;

    private WebhookLoader $webhookLoader;

    private AclRoleLoader $aclRoleLoader;

    protected function setUp(): void
    {
        $this->registry = new StaticDefinitionInstanceRegistry(
            [WebhookDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $this->validator = static::createStub(SubscriptionValidator::class);
        $this->validator->method('validate')->willReturn(new SubscriptionRefusals());

        $this->webhookLoader = static::createStub(WebhookLoader::class);
        $this->webhookLoader->method('getSubscriptions')->willReturn([]);

        $this->aclRoleLoader = static::createStub(AclRoleLoader::class);
        $this->aclRoleLoader->method('getRoles')->willReturn([self::USER_ID => new AclRoles(false, ['role'])]);
        $this->aclRoleLoader->method('getPrivilegesForRoles')->willReturn([]);
    }

    public function testAnAppsWebhookCannotBeModifiedEvenByAnAdmin(): void
    {
        $update = $this->updateWebhook(['url' => 'https://example.com']);
        $this->storeWebhook($update, SubscriberType::App, Uuid::randomHex());

        $this->assertViolation($this->authorize([$update]), WebhookException::APP_WEBHOOK_NOT_MODIFIABLE);
        $this->assertViolation($this->authorize([$update], $this->userContext(Uuid::randomHex(), isAdmin: true)), WebhookException::APP_WEBHOOK_NOT_MODIFIABLE);
    }

    public function testOnlyAnAdminMaySwitchAnAppsWebhookOnOrOff(): void
    {
        $update = $this->updateWebhook(['active' => 1]);
        $this->storeWebhook($update, SubscriberType::App, Uuid::randomHex());

        static::assertSame([], $this->authorize([$update], $this->userContext(Uuid::randomHex(), isAdmin: true)));
        $this->assertViolation($this->authorize([$update]), WebhookException::APP_WEBHOOK_NOT_MODIFIABLE);
    }

    public function testAnAdminMayNotChangeMoreThanTheActiveFlagOfAnAppsWebhook(): void
    {
        $update = $this->updateWebhook(['active' => 1, 'url' => 'https://example.com']);
        $this->storeWebhook($update, SubscriberType::App, Uuid::randomHex());

        $this->assertViolation($this->authorize([$update], $this->userContext(Uuid::randomHex(), isAdmin: true)), WebhookException::APP_WEBHOOK_NOT_MODIFIABLE);
    }

    public function testOnlyTheOwningUserMayModify(): void
    {
        $update = $this->updateWebhook(['url' => 'https://example.com']);
        $this->storeWebhook($update, SubscriberType::User, self::USER_ID);

        static::assertSame([], $this->authorize([$update]));
        $this->assertViolation($this->authorize([$update], $this->userContext(Uuid::randomHex())), WebhookException::WEBHOOK_NOT_OWNED);
    }

    public function testOnlyTheOwningIntegrationMayModify(): void
    {
        $integrationId = Uuid::randomHex();
        $update = $this->updateWebhook(['url' => 'https://example.com']);
        $this->storeWebhook($update, SubscriberType::Integration, $integrationId);

        static::assertSame([], $this->authorize([$update], new Context(new AdminApiSource(null, $integrationId))));
        $this->assertViolation($this->authorize([$update], new Context(new AdminApiSource(null, Uuid::randomHex()))), WebhookException::WEBHOOK_NOT_OWNED);
    }

    public function testADeleteIsCheckedLikeAnUpdate(): void
    {
        $delete = new DeleteCommand($this->webhookDefinition(), ['id' => Uuid::randomBytes()], static::createStub(EntityExistence::class));
        $this->storeWebhook($delete, SubscriberType::User, Uuid::randomHex());

        $this->assertViolation($this->authorize([$delete]), WebhookException::WEBHOOK_NOT_OWNED);
    }

    public function testOnlyAnAdminMayModifyAWebhookWithoutOwner(): void
    {
        $update = $this->updateWebhook(['url' => 'https://example.com']);

        $this->assertViolation($this->authorize([$update]), WebhookException::WEBHOOK_NOT_OWNED);
        static::assertSame([], $this->authorize([$update], $this->userContext(Uuid::randomHex(), isAdmin: true)));
    }

    public function testAnAdminMayModifyAWebhookItDoesNotOwn(): void
    {
        $update = $this->updateWebhook(['url' => 'https://example.com']);
        $this->storeWebhook($update, SubscriberType::User, Uuid::randomHex());

        static::assertSame([], $this->authorize([$update], $this->userContext(Uuid::randomHex(), isAdmin: true)));
    }

    public function testARejectedModificationIsNotCheckedFurther(): void
    {
        $update = $this->updateWebhook(['event_name' => 'product.written']);
        $this->storeWebhook($update, SubscriberType::User, Uuid::randomHex());

        $this->validator = $this->createMock(SubscriptionValidator::class);
        $this->validator->expects($this->never())->method('validate');

        $this->assertViolation($this->authorize([$update]), WebhookException::WEBHOOK_NOT_OWNED);
    }

    public function testAnEmptyAclRoleListIsRejected(): void
    {
        $violations = $this->authorize([$this->insertWebhook(aclRoleIds: [])]);

        $this->assertAclRoleIdsViolation($violations, 'aclRoleIds must not be empty. Leave it out to use all of the owner\'s roles.');
    }

    public function testEmptyAclRoleEntriesAreRejected(): void
    {
        $violations = $this->authorize([$this->insertWebhook(aclRoleIds: ['role', '', '0'])]);

        $this->assertAclRoleIdsViolation($violations, 'aclRoleIds must not contain empty entries.');
    }

    public function testOnlyEventAndAclRoleChangesAreChecked(): void
    {
        $update = $this->updateWebhook(['url' => 'https://example.com']);
        $this->storeWebhook($update, SubscriberType::User, self::USER_ID);

        $this->validator = $this->createMock(SubscriptionValidator::class);
        $this->validator->expects($this->never())->method('validate');

        static::assertSame([], $this->authorize([$update]));
    }

    public function testSystemWritesAreNotChecked(): void
    {
        $this->webhookLoader = $this->createMock(WebhookLoader::class);
        $this->webhookLoader->expects($this->never())->method('getSubscriptions');

        $this->validator = $this->createMock(SubscriptionValidator::class);
        $this->validator->expects($this->never())->method('validate');

        $violations = $this->authorize(
            [$this->updateWebhook(['event_name' => 'product.written']), $this->insertWebhook(aclRoleIds: [])],
            Context::createDefaultContext()
        );

        static::assertSame([], $violations);
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

        $violations = $this->authorize([$product, $order, $customer]);

        static::assertCount(3, $violations);
        static::assertSame(WebhookException::webhookEventNotPermitted('product.written')->getMessage(), $violations[0]->getMessage());
        static::assertSame(WebhookException::webhookEventNotPermitted('order.written')->getMessage(), $violations[1]->getMessage());
        static::assertSame(WebhookException::webhookEventPrivilegesMissing('customer.written', ['customer:read'])->getMessage(), $violations[2]->getMessage());
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

        $this->authorize([$insert]);
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

        $this->authorize([$insert]);
    }

    public function testAnUpdateIsCheckedWithTheStoredOwnersRolesAndTheNewEvent(): void
    {
        $update = $this->updateWebhook(['event_name' => 'order.written']);

        $this->webhookLoader = $this->createMock(WebhookLoader::class);
        $this->webhookLoader->expects($this->once())
            ->method('getSubscriptions')
            ->with([self::id($update)])
            ->willReturn([
                self::id($update) => new WebhookSubscription(self::id($update), SubscriberType::User, self::USER_ID, 'product.written', null),
            ]);

        $this->aclRoleLoader = $this->createMock(AclRoleLoader::class);
        $this->aclRoleLoader->expects($this->once())
            ->method('getRoles')
            ->with([self::USER_ID])
            ->willReturn([self::USER_ID => new AclRoles(false, ['role'])]);
        $this->aclRoleLoader->method('getPrivilegesForRoles')->willReturn(['role' => new AclPrivilegeCollection(['order:read'])]);

        $this->validator = $this->createMock(SubscriptionValidator::class);
        $this->validator->expects($this->once())
            ->method('validate')
            ->with([self::id($update) => 'order.written'], [self::id($update) => ['order:read']])
            ->willReturn(new SubscriptionRefusals());

        static::assertSame([], $this->authorize([$update]));
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

        $this->authorize([$insert]);
    }

    public function testAclRolesTheOwnerDoesNotHoldAreRejected(): void
    {
        $this->aclRoleLoader = static::createStub(AclRoleLoader::class);
        $this->aclRoleLoader->method('getRoles')->willReturn([self::USER_ID => new AclRoles(false, ['role'])]);
        $this->aclRoleLoader->method('getPrivilegesForRoles')->willReturn([
            'role' => new AclPrivilegeCollection([]),
            'other-role' => new AclPrivilegeCollection([]),
        ]);

        $violations = $this->authorize([$this->insertWebhook(aclRoleIds: ['role', 'other-role'])]);

        $this->assertAclRoleIdsViolation($violations, 'Role "other-role" is not held by the webhook\'s owner.');
    }

    public function testAnUnrestrictedOwnerMayListAnyExistingRole(): void
    {
        $this->aclRoleLoader = static::createStub(AclRoleLoader::class);
        $this->aclRoleLoader->method('getRoles')->willReturn([self::USER_ID => new AclRoles(true, [])]);
        $this->aclRoleLoader->method('getPrivilegesForRoles')->willReturn(['any-role' => new AclPrivilegeCollection([])]);

        static::assertSame([], $this->authorize([$this->insertWebhook(aclRoleIds: ['any-role'])]));
    }

    public function testRolesThatDoNotExistAreRejectedEvenForAnUnrestrictedOwner(): void
    {
        $this->aclRoleLoader = static::createStub(AclRoleLoader::class);
        $this->aclRoleLoader->method('getRoles')->willReturn([self::USER_ID => new AclRoles(true, [])]);
        $this->aclRoleLoader->method('getPrivilegesForRoles')->willReturn([]);

        $violations = $this->authorize([$this->insertWebhook(aclRoleIds: ['missing-role'])]);

        $this->assertAclRoleIdsViolation($violations, 'Role "missing-role" does not exist.');
    }

    public function testStoredAclRolesAreOnlyRejectedWhenWritten(): void
    {
        $update = $this->updateWebhook(['event_name' => 'product.written']);
        $this->webhookLoader = static::createStub(WebhookLoader::class);
        $this->webhookLoader->method('getSubscriptions')->willReturn([
            self::id($update) => new WebhookSubscription(self::id($update), SubscriberType::User, self::USER_ID, 'order.written', ['role', 'lost-role']),
        ]);

        static::assertSame([], $this->authorize([$update]));
    }

    /**
     * @param list<WriteCommand> $commands
     *
     * @return list<HttpException>
     */
    private function authorize(array $commands, ?Context $context = null): array
    {
        $keyed = [];
        foreach ($commands as $command) {
            $keyed[self::id($command)] = $command;
        }

        return (new WriteAuthorizer($this->validator, $this->webhookLoader, $this->aclRoleLoader))
            ->getViolations($keyed, $context ?? $this->userContext(self::USER_ID));
    }

    private function storeWebhook(WriteCommand $command, SubscriberType $ownerType, string $ownerId): void
    {
        $this->webhookLoader = static::createStub(WebhookLoader::class);
        $this->webhookLoader->method('getSubscriptions')->willReturn([
            self::id($command) => new WebhookSubscription(self::id($command), $ownerType, $ownerId, 'product.written', null),
        ]);
    }

    private function userContext(string $userId, bool $isAdmin = false): Context
    {
        $source = new AdminApiSource($userId);
        $source->setIsAdmin($isAdmin);

        return new Context($source);
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
     * @param list<HttpException> $violations
     */
    private function assertViolation(array $violations, string $errorCode): void
    {
        static::assertCount(1, $violations);
        static::assertSame($errorCode, $violations[0]->getErrorCode());
    }

    /**
     * @param list<HttpException> $violations
     */
    private function assertAclRoleIdsViolation(array $violations, string $message): void
    {
        static::assertCount(1, $violations);
        static::assertInstanceOf(WriteConstraintViolationException::class, $violations[0]);

        $constraintViolations = $violations[0]->getViolations();
        static::assertCount(1, $constraintViolations);
        static::assertSame('/aclRoleIds', $constraintViolations->get(0)->getPropertyPath());
        static::assertSame($message, $constraintViolations->get(0)->getMessage());
    }
}
