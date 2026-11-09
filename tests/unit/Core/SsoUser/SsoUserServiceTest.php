<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\SsoUser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Sso\SsoUser\SsoUserService;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\User\UserCollection;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SsoUserService::class)]
class SsoUserServiceTest extends TestCase
{
    public function testInviteUserWillCreateNewUser(): void
    {
        $userRepository = StaticEntityRepository::of(UserCollection::class, [[]]);

        $ssoUserService = new SsoUserService($userRepository);

        $ssoUserService->inviteUser('test@example.com', Uuid::randomHex(), Context::createDefaultContext());

        static::assertCount(1, $userRepository->searchedCriteria);
        static::assertCount(1, $userRepository->creates);
    }

    public function testInviteUserWillNotCreateNewUser(): void
    {
        $userRepository = StaticEntityRepository::of(UserCollection::class, [[Uuid::randomHex()]]);

        $ssoUserService = new SsoUserService($userRepository);

        $ssoUserService->inviteUser('test@example.com', Uuid::randomHex(), Context::createDefaultContext());

        static::assertCount(1, $userRepository->searchedCriteria);
        static::assertSame([], $userRepository->creates);
    }
}
