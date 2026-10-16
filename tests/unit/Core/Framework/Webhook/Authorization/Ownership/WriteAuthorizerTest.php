<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Authorization\Ownership;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Authorization\Ownership\Ownership;
use Shopware\Core\Framework\Webhook\Authorization\Ownership\WriteAuthorizer;
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
            new Ownership(self::WEBHOOK_ID, Uuid::randomHex()),
            $this->userSource(Uuid::randomHex())
        );

        $this->assertViolation($violations, WebhookException::APP_WEBHOOK_NOT_MODIFIABLE);
    }

    public function testAppWebhookCannotBeModifiedEvenByAnAdmin(): void
    {
        $violations = $this->authorize(
            new Ownership(self::WEBHOOK_ID, Uuid::randomHex()),
            $this->userSource(Uuid::randomHex(), isAdmin: true)
        );

        $this->assertViolation($violations, WebhookException::APP_WEBHOOK_NOT_MODIFIABLE);
    }

    private function userSource(string $userId, bool $isAdmin = false): AdminApiSource
    {
        $source = new AdminApiSource($userId);
        $source->setIsAdmin($isAdmin);

        return $source;
    }

    /**
     * @return list<WebhookException>
     */
    private function authorize(Ownership $ownership, AdminApiSource $source): array
    {
        $loader = $this->createMock(WebhookLoader::class);
        $loader->expects($this->once())
            ->method('getOwnership')
            ->with([self::WEBHOOK_ID])
            ->willReturn([$ownership]);

        $context = new Context($source);

        return (new WriteAuthorizer($loader))->getModificationViolations([self::WEBHOOK_ID], $context);
    }

    /**
     * @param list<WebhookException> $violations
     */
    private function assertViolation(array $violations, string $errorCode): void
    {
        static::assertCount(1, $violations);
        static::assertSame($errorCode, $violations[0]->getErrorCode());
        static::assertStringContainsString(self::WEBHOOK_ID, $violations[0]->getMessage());
    }
}
