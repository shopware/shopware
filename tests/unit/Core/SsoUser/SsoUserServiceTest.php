<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\SsoUser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Sso\SsoUser\SsoUserService;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SsoUserService::class)]
class SsoUserServiceTest extends TestCase
{
    public function testInviteUserWillCreateNewUser(): void
    {
        $userRepository = $this->createMock(EntityRepository::class);
        $userRepository->expects($this->once())->method('searchIds')->willReturn(
            new IdSearchResult(0, [], new Criteria(), Context::createDefaultContext())
        );
        $userRepository->expects($this->once())->method('create');

        $ssoUserService = new SsoUserService($userRepository);

        $ssoUserService->inviteUser('test@example.com', Uuid::randomHex(), Context::createDefaultContext());
    }

    public function testInviteUserWillNotCreateNewUser(): void
    {
        $userId = Uuid::randomHex();

        $userRepository = $this->createMock(EntityRepository::class);
        $userRepository->expects($this->once())->method('searchIds')->willReturn(
            new IdSearchResult(1, [$userId => ['primaryKey' => $userId, 'data' => []]], new Criteria(), Context::createDefaultContext())
        );
        $userRepository->expects($this->never())->method('create');

        $ssoUserService = new SsoUserService($userRepository);

        $ssoUserService->inviteUser('test@example.com', Uuid::randomHex(), Context::createDefaultContext());
    }
}
