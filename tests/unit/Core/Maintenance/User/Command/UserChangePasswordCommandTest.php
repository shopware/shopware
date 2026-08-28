<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\User\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Maintenance\User\Command\UserChangePasswordCommand;
use Shopware\Core\System\User\UserCollection;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(UserChangePasswordCommand::class)]
class UserChangePasswordCommandTest extends TestCase
{
    private const TEST_USERNAME = 'shopware';
    private const TEST_PASSWORD = 'shopwarePassword';

    public function testUnknownUser(): void
    {
        $userRepo = StaticEntityRepository::of(UserCollection::class, [[]]);
        $command = new UserChangePasswordCommand($userRepo);

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'username' => self::TEST_USERNAME,
            '--password' => self::TEST_PASSWORD,
        ]);

        $expected = 'The user "' . self::TEST_USERNAME . '" does not exist.';
        static::assertStringContainsString($expected, $commandTester->getDisplay());
        static::assertSame(1, $commandTester->getStatusCode());
    }

    public function testKnownUser(): void
    {
        $userId = Uuid::randomHex();
        $newPassword = Uuid::randomHex();

        $userRepo = StaticEntityRepository::of(UserCollection::class, [[$userId]]);
        $command = new UserChangePasswordCommand($userRepo);

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'username' => self::TEST_USERNAME,
            '--password' => $newPassword,
        ]);

        $expected = 'The password of user "' . self::TEST_USERNAME . '" has been changed successfully.';
        static::assertStringContainsString($expected, $commandTester->getDisplay());
        static::assertSame(0, $commandTester->getStatusCode());

        $updates = $userRepo->updates;
        $updatedData = $updates[0][0];
        static::assertSame($userId, $updatedData['id']);
        static::assertSame($newPassword, $updatedData['password']);
    }

    public function testEmptyPasswordOption(): void
    {
        $userRepo = static::createStub(EntityRepository::class);
        $command = new UserChangePasswordCommand($userRepo);

        $commandTester = new CommandTester($command);

        $violations = Validation::createValidator()->validate('', new NotBlank());
        $this->expectExceptionObject(new ValidationFailedException('', $violations));

        $commandTester->setInputs(['', '', '']);
        $commandTester->execute([
            'username' => self::TEST_USERNAME,
        ]);
    }
}
