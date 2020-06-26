<?php

declare(strict_types=1);

namespace Shopware\Core\System\Test\User\Command;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\User\Command\UserChangePasswordCommand;
use Shopware\Core\System\User\UserEntity;
use Symfony\Component\Console\Tester\CommandTester;

class UserChangePasswordCommandTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $userRepository;

    /**
     * @var Context
     */
    private $context;

    protected function setUp(): void
    {
        $this->userRepository = $this->getContainer()->get('user.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testUnknownUser(): void
    {
        $commandTester = new CommandTester($this->getContainer()->get(UserChangePasswordCommand::class));
        $commandTester->execute(['username' => 'shopware']);

        static::assertIsInt(mb_strpos($commandTester->getDisplay(), 'The user "shopware" does not exist.'));
        static::assertEquals(1, $commandTester->getStatusCode());
    }

    public function testKnownUser(): void
    {
        $this->createUser('shopware', 'shopwarePassword');

        $commandTester = new CommandTester($this->getContainer()->get(UserChangePasswordCommand::class));
        $commandTester->execute([
            'username' => 'shopware',
            '--password' => 'newPassword',
        ]);

        static::assertIsInt(mb_strpos(
            $commandTester->getDisplay(),
            'The password of user "shopware" has been changed succesfully.'
        ));
        static::assertEquals(0, $commandTester->getStatusCode());
    }

    public function testItChangesThePassword(): void
    {
        $uuid = $this->createUser('shopware', 'shopwarePassword');

        $newPassword = Uuid::randomHex();
        $commandTester = new CommandTester($this->getContainer()->get(UserChangePasswordCommand::class));
        $commandTester->execute([
            'username' => 'shopware',
            '--password' => $newPassword,
        ]);

        /** @var UserEntity $user */
        $user = $this->userRepository->search(new Criteria([$uuid]), $this->context)->first();

        $passwordVerify = password_verify($newPassword, $user->getPassword());
        static::assertTrue($passwordVerify);
    }

    private function createUser(string $username, string $password): string
    {
        $uuid = Uuid::randomHex();

        $this->userRepository->create([
            [
                'id' => $uuid,
                'localeId' => $this->getLocaleIdOfSystemLanguage(),
                'username' => $username,
                'password' => $password,
                'firstName' => sprintf('Foo%s', Uuid::randomHex()),
                'lastName' => sprintf('Bar%s', Uuid::randomHex()),
                'email' => sprintf('%s@foo.bar', $uuid),
            ],
        ], $this->context);

        return $uuid;
    }
}
