<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Consent\ConsentScope;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\ConsentException;
use Shopware\Core\System\Consent\ConsentScope\AdminUser;
use Shopware\Core\System\Consent\ConsentScope\System;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(AdminUser::class)]
class SystemTest extends TestCase
{
    public function testScope(): void
    {
        $scope = new System();

        static::assertSame('system', $scope->getName());
    }

    public function testScopeIdentifier(): void
    {
        $context = Context::createDefaultContext();

        $scope = new System();
        static::assertSame('system', $scope->resolveIdentifier($context));
    }

    public function testActorIdentifierThrowsExceptionWhenSourceIsNotAdminApi(): void
    {
        self::expectExceptionObject(ConsentException::cannotResolveScope(System::NAME));

        $scope = new System();
        $scope->resolveActorIdentifier(Context::createDefaultContext());
    }

    public function testActorIdentifierThrowsExceptionWhenUserIdIsNull(): void
    {
        self::expectExceptionObject(ConsentException::cannotResolveScope(System::NAME));

        $source = new AdminApiSource(null);
        $context = new Context($source);

        $scope = new System();
        $scope->resolveActorIdentifier($context);
    }

    public function testActorIdentifier(): void
    {
        $source = new AdminApiSource('user-123');
        $context = new Context($source);

        $scope = new System();
        static::assertSame('user-123', $scope->resolveActorIdentifier($context));
    }
}
