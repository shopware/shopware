<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\DTO\Service;
use Shopware\Core\Service\State;
use Shopware\Tests\Unit\Core\Framework\App\AppFixture;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Service::class)]
class ServiceTest extends TestCase
{
    public function testFromApp(): void
    {
        $createdAt = new \DateTimeImmutable('2026-01-01 12:00:00');
        $updatedAt = new \DateTimeImmutable('2026-01-02 12:00:00');
        $app = AppFixture::createAppEntity(name: 'MyService', id: 'service-id');
        $app->setLabel('My plain label');
        $app->setTranslated([
            'label' => 'My translated label',
            'description' => 'My translated description',
        ]);
        $app->setIcon('service-icon');
        $app->setCreatedAt($createdAt);
        $app->setUpdatedAt($updatedAt);
        $app->setRequestedPrivileges(['product:read', 'order:read']);
        $app->setAllowedHosts(['https://example.com', 'https://cdn.example.com']);
        $app->setSourceConfig(['requirements' => ['shopware_account', 'service_consent']]);

        $aclRole = new AclRoleEntity();
        $aclRole->setPrivileges(['product:read', 'customer:read']);
        $app->setAclRole($aclRole);

        $service = Service::fromApp($app);

        static::assertSame('service-id', $service->id);
        static::assertSame('MyService', $service->name);
        static::assertSame('My translated label', $service->label);
        static::assertTrue($service->active);
        static::assertSame('service-icon', $service->icon);
        static::assertSame('My translated description', $service->description);
        static::assertSame($updatedAt, $service->updatedAt);
        static::assertSame('1.0.0', $service->version);
        static::assertSame('acl-role-id', $service->aclRoleId);
        static::assertSame(['product:read', 'order:read'], $service->requestedPrivileges);
        static::assertSame(['product:read', 'customer:read'], $service->privileges);
        static::assertSame(['product:read', 'order:read', 'customer:read'], $service->getAllPrivileges());
        static::assertSame(State::PENDING_PERMISSIONS, $service->state);
        static::assertSame(['https://example.com', 'https://cdn.example.com'], $service->domains);
        static::assertSame(['shopware_account', 'service_consent'], $service->requirements);
    }

    public function testFromAppUsesCreatedAtWhenUpdatedAtIsMissing(): void
    {
        $app = AppFixture::createAppEntity(name: 'MyService', active: false);

        $service = Service::fromApp($app);

        static::assertNull($service->description);
        static::assertSame($app->getCreatedAt(), $service->updatedAt);
        static::assertSame([], $service->privileges);
        static::assertSame([], $service->domains);
        static::assertSame([], $service->requirements);
        static::assertSame(State::INACTIVE, $service->state);
    }

    public function testLabelUsesTranslationWhenRequestedLanguageHasNoLabel(): void
    {
        $app = AppFixture::createAppEntity(name: 'MyService');
        $app->setLabel(null);
        $app->setTranslated(['label' => 'My translated label']);

        static::assertSame('My translated label', Service::fromApp($app)->label);
    }

    public function testLabelFallsBackToNameWhenNoTranslationResolves(): void
    {
        $app = AppFixture::createAppEntity(name: 'MyService');
        $app->setLabel(null);
        $app->setTranslated([]);

        static::assertSame('MyService', Service::fromApp($app)->label);
    }

    public function testLabelFallsBackToNameWhenTranslationIsEmpty(): void
    {
        $app = AppFixture::createAppEntity(name: 'MyService');
        $app->setLabel(null);
        $app->setTranslated(['label' => '']);

        static::assertSame('MyService', Service::fromApp($app)->label);
    }
}
