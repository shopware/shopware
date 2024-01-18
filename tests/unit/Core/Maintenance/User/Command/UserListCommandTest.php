<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\User\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleCollection;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleEntity;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Maintenance\User\Command\UserListCommand;
use Shopware\Core\System\User\UserCollection;
use Shopware\Core\System\User\UserEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(UserListCommand::class)]
class UserListCommandTest extends TestCase
{
    public function testWithNoUsers(): void
    {
        /** @var StaticEntityRepository<UserCollection> $repo */
        $repo = new StaticEntityRepository(
            [
                new UserCollection(),
            ]
        );

        $command = new UserListCommand($repo);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();

        $output = $commandTester->getDisplay();

        static::assertStringContainsString('There are no users', $output);
    }

    public function testWithUsers(): void
    {
        $ids = new IdsCollection();

        /** @var StaticEntityRepository<UserCollection> $repo */
        $repo = new StaticEntityRepository([
            new UserCollection([
                $this->createUser($ids->get('user1'), 'guy@shopware.com', 'guy', 'Guy', 'Marbello'),
                $this->createUser($ids->get('user2'), 'jen@shopware.com', 'jen', 'Jen', 'Dalimil', ['Moderator', 'CS']),
            ]),
        ]);

        $command = new UserListCommand($repo);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();

        $output = $commandTester->getDisplay();

        static::assertStringContainsString('Guy Marbello', $output);
        static::assertStringContainsString('Jen Dalimil', $output);
    }

    public function testWithJson(): void
    {
        $ids = new IdsCollection();

        /** @var StaticEntityRepository<UserCollection> $repo */
        $repo = new StaticEntityRepository([
            new UserCollection([
                $this->createUser($ids->get('user1'), 'guy@shopware.com', 'guy', 'Guy', 'Marbello'),
                $this->createUser($ids->get('user2'), 'jen@shopware.com', 'jen', 'Jen', 'Dalimil', ['Moderator', 'CS']),
            ]),
        ]);

        $command = new UserListCommand($repo);
        $commandTester = new CommandTester($command);
        $commandTester->execute(['--json' => true]);

        $commandTester->assertCommandIsSuccessful();

        $output = $commandTester->getDisplay();

        static::assertTrue(json_validate($output));
        static::assertStringContainsString('Guy Marbello', $output);
        static::assertStringContainsString('Jen Dalimil', $output);
    }

    /**
     * @param array<string> $roles
     */
    private function createUser(
        string $id,
        string $email,
        string $username,
        string $firstName,
        string $secondName,
        ?array $roles = null,
    ): UserEntity {
        $user = new UserEntity();
        $user->setId($id);
        $user->setEmail($email);
        $user->setActive(true);
        $user->setUsername($username);
        $user->setFirstName($firstName);
        $user->setLastName($secondName);
        $user->setAdmin($roles === null);
        $user->setCreatedAt(new \DateTime());

        if ($roles) {
            $user->setAclRoles(new AclRoleCollection(array_map(static function (string $role): AclRoleEntity {
                $aclRole = new AclRoleEntity();
                $aclRole->setId(Uuid::randomHex());
                $aclRole->setName($role);

                return $aclRole;
            }, $roles)));
        }

        return $user;
    }
}
