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
    private const TEST_USERNAME = 'shopware';
    private const TEST_PASSWORD = 'shopwarePassword';

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
        $commandTester->execute(['username' => self::TEST_USERNAME]);

        $expected = 'The user "' . self::TEST_USERNAME . '" does not exist.';
        static::assertStringContainsString($expected, $commandTester->getDisplay());
        static::assertEquals(1, $commandTester->getStatusCode());
    }

    public function testKnownUser(): void
    {
        $userId = $this->createUser();
        $newPassword = Uuid::randomHex();

        $commandTester = new CommandTester($this->getContainer()->get(UserChangePasswordCommand::class));
        $commandTester->execute([
            'username' => self::TEST_USERNAME,
            '--password' => $newPassword,
        ]);

        $expected = 'The password of user "' . self::TEST_USERNAME . '" has been changed successfully.';
        static::assertStringContainsString($expected, $commandTester->getDisplay());
        static::assertEquals(0, $commandTester->getStatusCode());

        /** @var UserEntity $user */
        $user = $this->userRepository->search(new Criteria([$userId]), $this->context)->first();

        $passwordVerify = password_verify($newPassword, $user->getPassword());
        static::assertTrue($passwordVerify);
    }

    private function createUser(): string
    {
        $uuid = Uuid::randomHex();

        $this->userRepository->create([
            [
                'id' => $uuid,
                'localeId' => $this->getLocaleIdOfSystemLanguage(),
                'username' => self::TEST_USERNAME,
                'password' => self::TEST_PASSWORD,
                'firstName' => sprintf('Foo%s', Uuid::randomHex()),
                'lastName' => sprintf('Bar%s', Uuid::randomHex()),
                'email' => sprintf('%s@foo.bar', $uuid),
            ],
        ], $this->context);

        return $uuid;
    }
}
