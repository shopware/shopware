<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\User\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleCollection;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Maintenance\MaintenanceException;
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
        $repo = new StaticEntityRepository([new UserCollection()]);

        $command = new UserListCommand($repo);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();

        $output = $commandTester->getDisplay();

        static::assertStringContainsString('There are no users', $output);
    }

    public function testWithUsers(): void
    {
        $commandTester = $this->prepareCommandTester();
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();

        $output = $commandTester->getDisplay();

        static::assertStringContainsString('Guy Marbello', $output);
        static::assertStringContainsString('Jen Dalimil', $output);
    }

    public function testAclRolesNotLoadedException(): void
    {
        $userName = 'guy';
        $userId = Uuid::randomHex();
        /** @var StaticEntityRepository<UserCollection> $repo */
        $repo = new StaticEntityRepository([
            new UserCollection([
                $this->createUser('guy@shopware.com', $userName, 'Guy', 'Marbello', id: $userId),
            ]),
        ]);

        $command = new UserListCommand($repo);
        $commandTester = new CommandTester($command);

        $this->expectExceptionObject(MaintenanceException::aclRolesNotLoaded($userId, $userName));
        $commandTester->execute([]);
    }

    public function testWithJson(): void
    {
        $commandTester = $this->prepareCommandTester();
        $commandTester->execute(['--json' => true]);

        $commandTester->assertCommandIsSuccessful();

        $output = $commandTester->getDisplay();

        static::assertTrue(json_validate($output));
        static::assertStringContainsString('Guy Marbello', $output);
        static::assertStringContainsString('Jen Dalimil', $output);
    }

    private function prepareCommandTester(): CommandTester
    {
        /** @var StaticEntityRepository<UserCollection> $repo */
        $repo = new StaticEntityRepository([
            new UserCollection([
                $this->createUser('guy@shopware.com', 'guy', 'Guy', 'Marbello', true),
                $this->createUser('jen@shopware.com', 'jen', 'Jen', 'Dalimil', false, ['Moderator', 'CS']),
            ]),
        ]);

        $command = new UserListCommand($repo);

        return new CommandTester($command);
    }

    /**
     * @param array<string> $roles
     */
    private function createUser(
        string $email,
        string $username,
        string $firstName,
        string $secondName,
        bool $isAdmin = false,
        ?array $roles = null,
        ?string $id = null,
    ): UserEntity {
        $user = new UserEntity();
        $user->setId($id ?? Uuid::randomHex());
        $user->setEmail($email);
        $user->setActive(true);
        $user->setUsername($username);
        $user->setFirstName($firstName);
        $user->setLastName($secondName);
        $user->setAdmin($isAdmin);
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
