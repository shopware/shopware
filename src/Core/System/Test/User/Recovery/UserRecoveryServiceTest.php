<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\User\Recovery;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\System\User\Aggregate\UserRecovery\UserRecoveryEntity;
use Shopware\Core\System\User\Recovery\UserRecoveryService;
use Shopware\Core\System\User\UserEntity;

class UserRecoveryServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const VALID_EMAIL = 'info@shopware.com';

    /**
     * @var UserRecoveryService
     */
    private $userRecoveryService;

    /**
     * @var EntityRepositoryInterface
     */
    private $userRecoveryRepo;

    /**
     * @var EntityRepositoryInterface
     */
    private $userRepo;

    /**
     * @var Context
     */
    private $context;

    protected function setUp(): void
    {
        $container = $this->getContainer();
        $this->userRepo = $container->get('user.repository');
        $this->userRecoveryRepo = $container->get('user_recovery.repository');
        $this->userRecoveryService = new UserRecoveryService(
            $this->userRecoveryRepo,
            $this->userRepo,
            $container->get('router'),
            $container->get('Shopware\Core\Framework\Event\BusinessEventDispatcher')
        );
        $this->context = Context::createDefaultContext();
    }

    protected function tearDown(): void
    {
        $userRecoveryRepo = $this->getContainer()->get('user_recovery.repository');
        $ids = $userRecoveryRepo->searchIds(new Criteria(), $this->context)->getIds();

        foreach ($ids as $id) {
            $deleteData = [
                'id' => $id,
            ];

            $userRecoveryRepo->delete([$deleteData], $this->context);
        }
    }

    public function testGenerateUserRecoveryWithExistingUser(): void
    {
        $this->createRecovery(static::VALID_EMAIL);

        $userRecovery = $this->userRecoveryRepo->search(new Criteria(), $this->context)->first();
        static::assertInstanceOf(UserRecoveryEntity::class, $userRecovery);
    }

    public function testGenerateUserRecoveryWithoutExistingUser(): void
    {
        $this->createRecovery('foo@bar.com');

        $userRecovery = $this->userRecoveryRepo->search(new Criteria(), $this->context)->first();
        static::assertNull($userRecovery);
    }

    /**
     * @dataProvider dataProviderTestCheckHash
     */
    public function testCheckHash(\DateInterval $timeInterval, string $hash, bool $expectedResult): void
    {
        $user = $this->userRepo->search(new Criteria(), $this->context)->first();

        static::assertInstanceOf(UserEntity::class, $user);

        $createdTime = (new \DateTime())->sub($timeInterval);

        $userId = $user->getId();
        $creatData = [
            'createdAt' => $createdTime,
            'hash' => $hash,
            'userId' => $userId,
        ];

        $this->userRecoveryRepo->create([$creatData], $this->context);

        static::assertSame($expectedResult, $this->userRecoveryService->checkHash($hash, $this->context));
    }

    public function dataProviderTestCheckHash(): array
    {
        return [
            [
                new \DateInterval('PT0H'),
                Random::getAlphanumericString(32),
                true,
            ],
            [
                new \DateInterval('PT3H'),
                Random::getAlphanumericString(32),
                false,
            ],
            [
                new \DateInterval('PT1H'),
                Random::getAlphanumericString(32),
                true,
            ],
            [
                new \DateInterval('PT1H58M'),
                Random::getAlphanumericString(32),
                true,
            ],
            [
                new \DateInterval('PT2H'),
                Random::getAlphanumericString(32),
                false,
            ],
            [
                new \DateInterval('PT2H1M'),
                Random::getAlphanumericString(32),
                false,
            ],
        ];
    }

    public function testUpdatePassword(): void
    {
        $this->createRecovery(static::VALID_EMAIL);

        $hash = $this->userRecoveryRepo->search(new Criteria(), $this->context)->first()->getHash();

        $passwordBefore = $this->userRepo->search(new Criteria(), $this->context)->first()->getPassword();

        $this->userRecoveryService->updatePassword($hash, 'newPassword', $this->context);

        $passwordAfter = $this->userRepo->search(new Criteria(), $this->context)->first()->getPassword();

        static::assertNotEquals($passwordBefore, $passwordAfter);
    }

    private function createRecovery(string $email): void
    {
        $this->userRecoveryService->generateUserRecovery(
            $email,
            Context::createDefaultContext()
        );
    }
}
