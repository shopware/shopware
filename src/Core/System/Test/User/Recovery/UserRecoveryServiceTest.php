<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\User\Recovery;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\System\User\Aggregate\UserRecovery\UserRecoveryEntity;
use Shopware\Core\System\User\Recovery\UserRecoveryRequestEvent;
use Shopware\Core\System\User\Recovery\UserRecoveryService;
use Shopware\Core\System\User\UserEntity;

/**
 * @internal
 */
#[Package('system-settings')]
class UserRecoveryServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const VALID_EMAIL = 'info@shopware.com';

    private UserRecoveryService $userRecoveryService;

    private EntityRepository $userRecoveryRepo;

    private EntityRepository $userRepo;

    private Context $context;

    protected function setUp(): void
    {
        $container = $this->getContainer();
        $this->userRepo = $container->get('user.repository');
        $this->userRecoveryRepo = $container->get('user_recovery.repository');
        $this->userRecoveryService = $container->get(UserRecoveryService::class);
        $this->context = Context::createDefaultContext();
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

    public static function dataProviderTestCheckHash(): array
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

    public function testGetUserByHash(): void
    {
        $this->createRecovery(static::VALID_EMAIL);

        $criteria = new Criteria();
        $criteria->setLimit(1);

        $hash = $this->userRecoveryRepo->search($criteria, $this->context)->first()->getHash();

        $invalid = $this->userRecoveryService->getUserByHash('invalid', $this->context);
        static::assertNull($invalid);

        $valid = $this->userRecoveryService->getUserByHash($hash, $this->context);
        static::assertNotNull($valid);
        static::assertEquals(static::VALID_EMAIL, $valid->getEmail());
    }

    public function testReEvaluateRules(): void
    {
        $validator = new RuleValidator();
        $this->getContainer()
            ->get('event_dispatcher')
            ->addListener(UserRecoveryRequestEvent::EVENT_NAME, $validator);

        $this->userRecoveryService->generateUserRecovery(
            static::VALID_EMAIL,
            Context::createDefaultContext()
        );

        static::assertInstanceOf(UserRecoveryRequestEvent::class, $validator->event);
        static::assertTrue(!empty($validator->event->getContext()->getRuleIds()));
    }

    private function createRecovery(string $email): void
    {
        $this->userRecoveryService->generateUserRecovery(
            $email,
            Context::createDefaultContext()
        );
    }
}

/**
 * @internal
 */
class RuleValidator extends CallableClass
{
    /**
     * @var UserRecoveryRequestEvent|null
     */
    public $event;

    public function __invoke(): void
    {
        $this->event = func_get_arg(0);
    }
}
