<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\SsoUser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Sso\SsoUser\SsoUserService;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\User\UserCollection;
use Shopware\Core\System\User\UserEntity;

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
        $userRepository->expects($this->once())->method('search');
        $userRepository->expects($this->once())->method('create');

        $ssoUserService = new SsoUserService($userRepository);

        $ssoUserService->inviteUser('test@example.com', Uuid::randomHex(), Context::createDefaultContext());
    }

    public function testInviteUserWillNotCreateNewUser(): void
    {
        $userEntity = new UserEntity();
        $userEntity->setUniqueIdentifier(Uuid::randomHex());
        $userEntity->setEmail('test@example.foo');
        $userEntity->setFirstName('FirstName');
        $userEntity->setLastName('LastName');
        $userEntity->setUsername('UserName');

        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->expects($this->once())->method('getEntities')->willReturn(new UserCollection([$userEntity]));

        $userRepository = $this->createMock(EntityRepository::class);
        $userRepository->expects($this->once())->method('search')->willReturn($searchResult);
        $userRepository->expects($this->never())->method('create');

        $ssoUserService = new SsoUserService($userRepository);

        $ssoUserService->inviteUser('test@example.com', Uuid::randomHex(), Context::createDefaultContext());
    }
}
