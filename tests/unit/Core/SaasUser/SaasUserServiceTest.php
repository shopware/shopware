<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\SaasUser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\SaasUser\SaasUserService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\User\UserCollection;
use Shopware\Core\System\User\UserEntity;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(SaasUserService::class)]
class SaasUserServiceTest extends TestCase
{
    public function testInviteUserWillCreateNewUser(): void
    {
        $userRepository = $this->createMock(EntityRepository::class);
        $userRepository->expects($this->once())->method('search');
        $userRepository->expects($this->once())->method('create');

        $saasUserService = new SaasUserService($userRepository);

        $saasUserService->inviteUser('test@example.com', Uuid::randomHex(), Context::createDefaultContext());
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

        $saasUserService = new SaasUserService($userRepository);

        $saasUserService->inviteUser('test@example.com', Uuid::randomHex(), Context::createDefaultContext());
    }
}
