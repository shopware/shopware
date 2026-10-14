<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Authorization\Ownership;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Authorization\Ownership\WriteAuthorizer;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\SubscriberType;
use Shopware\Core\Framework\Webhook\Authorization\WebhookSubscription;
use Shopware\Core\Framework\Webhook\Service\WebhookLoader;
use Shopware\Core\Framework\Webhook\WebhookException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WriteAuthorizer::class)]
class WriteAuthorizerTest extends TestCase
{
    private const WEBHOOK_ID = 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6';

    public function testAppWebhookCannotBeModified(): void
    {
        $violations = $this->authorize(
            new WebhookSubscription(self::WEBHOOK_ID, SubscriberType::App, Uuid::randomHex(), 'product.written', null),
            $this->userSource(Uuid::randomHex())
        );

        $this->assertViolation($violations, WebhookException::APP_WEBHOOK_NOT_MODIFIABLE);
    }

    public function testAppWebhookCannotBeModifiedEvenByAnAdmin(): void
    {
        $violations = $this->authorize(
            new WebhookSubscription(self::WEBHOOK_ID, SubscriberType::App, Uuid::randomHex(), 'product.written', null),
            $this->userSource(Uuid::randomHex(), isAdmin: true)
        );

        $this->assertViolation($violations, WebhookException::APP_WEBHOOK_NOT_MODIFIABLE);
    }

    public function testOwningUserMayModify(): void
    {
        $userId = Uuid::randomHex();

        $violations = $this->authorize(
            new WebhookSubscription(self::WEBHOOK_ID, SubscriberType::User, $userId, 'product.written', null),
            $this->userSource($userId)
        );

        static::assertSame([], $violations);
    }

    public function testAnotherUserMayNotModify(): void
    {
        $violations = $this->authorize(
            new WebhookSubscription(self::WEBHOOK_ID, SubscriberType::User, Uuid::randomHex(), 'product.written', null),
            $this->userSource(Uuid::randomHex())
        );

        $this->assertViolation($violations, WebhookException::WEBHOOK_NOT_OWNED);
    }

    public function testOwningIntegrationMayModify(): void
    {
        $integrationId = Uuid::randomHex();

        $violations = $this->authorize(
            new WebhookSubscription(self::WEBHOOK_ID, SubscriberType::Integration, $integrationId, 'product.written', null),
            new AdminApiSource(null, $integrationId)
        );

        static::assertSame([], $violations);
    }

    public function testAnotherIntegrationMayNotModify(): void
    {
        $violations = $this->authorize(
            new WebhookSubscription(self::WEBHOOK_ID, SubscriberType::Integration, Uuid::randomHex(), 'product.written', null),
            new AdminApiSource(null, Uuid::randomHex())
        );

        $this->assertViolation($violations, WebhookException::WEBHOOK_NOT_OWNED);
    }

    public function testWebhookWithoutOwnerMayNotBeModified(): void
    {
        $violations = $this->authorize(null, $this->userSource(Uuid::randomHex()));

        $this->assertViolation($violations, WebhookException::WEBHOOK_NOT_OWNED);
    }

    public function testAdminMayModifyAWebhookWithoutOwner(): void
    {
        $violations = $this->authorize(null, $this->userSource(Uuid::randomHex(), isAdmin: true));

        static::assertSame([], $violations);
    }

    public function testAdminMayModifyAWebhookItDoesNotOwn(): void
    {
        $violations = $this->authorize(
            new WebhookSubscription(self::WEBHOOK_ID, SubscriberType::User, Uuid::randomHex(), 'product.written', null),
            $this->userSource(Uuid::randomHex(), isAdmin: true)
        );

        static::assertSame([], $violations);
    }

    private function userSource(string $userId, bool $isAdmin = false): AdminApiSource
    {
        $source = new AdminApiSource($userId);
        $source->setIsAdmin($isAdmin);

        return $source;
    }

    /**
     * @return array<string, WebhookException>
     */
    private function authorize(?WebhookSubscription $subscription, AdminApiSource $source): array
    {
        $loader = $this->createMock(WebhookLoader::class);
        $loader->expects($this->once())
            ->method('getSubscriptions')
            ->with([self::WEBHOOK_ID])
            ->willReturn($subscription !== null ? [self::WEBHOOK_ID => $subscription] : []);

        $context = new Context($source);

        return (new WriteAuthorizer($loader))->getModificationViolations([self::WEBHOOK_ID], $context);
    }

    /**
     * @param array<string, WebhookException> $violations
     */
    private function assertViolation(array $violations, string $errorCode): void
    {
        static::assertCount(1, $violations);
        static::assertArrayHasKey(self::WEBHOOK_ID, $violations);
        static::assertSame($errorCode, $violations[self::WEBHOOK_ID]->getErrorCode());
        static::assertStringContainsString(self::WEBHOOK_ID, $violations[self::WEBHOOK_ID]->getMessage());
    }
}
