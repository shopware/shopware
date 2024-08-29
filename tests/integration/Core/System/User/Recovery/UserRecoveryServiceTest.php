<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\User\Recovery;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Maintenance\User\Service\UserProvisioner;
use Shopware\Core\System\User\Aggregate\UserRecovery\UserRecoveryCollection;
use Shopware\Core\System\User\Aggregate\UserRecovery\UserRecoveryEntity;
use Shopware\Core\System\User\Recovery\UserRecoveryRequestEvent;
use Shopware\Core\System\User\Recovery\UserRecoveryService;
use Shopware\Core\System\User\UserCollection;
use Shopware\Core\System\User\UserEntity;

/**
 * @internal
 */
#[Package('services-settings')]
class UserRecoveryServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const VALID_EMAIL = UserProvisioner::USER_EMAIL_FALLBACK;

    private UserRecoveryService $userRecoveryService;

    /**
     * @var EntityRepository<UserRecoveryCollection>
     */
    private EntityRepository $userRecoveryRepo;

    /**
     * @var EntityRepository<UserCollection>
     */
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

    public function testGenerateUserRecoveryWithNotExistingSalesChannelLanguage(): void
    {
        $this->createRecovery(self::VALID_EMAIL);

        $this->context->assign([
            'languageIdChain' => [Uuid::randomHex()],
        ]);

        $eventDispatched = false;
        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $this->addEventListener($dispatcher, UserRecoveryRequestEvent::EVENT_NAME, function (UserRecoveryRequestEvent $event) use (&$eventDispatched): void {
            $eventDispatched = true;
        });

        $this->userRecoveryService->generateUserRecovery(self::VALID_EMAIL, $this->context);

        static::assertTrue($eventDispatched);
    }

    public function testGenerateUserRecoveryWithExistingUser(): void
    {
        $this->createRecovery(self::VALID_EMAIL);

        $userRecovery = $this->userRecoveryRepo->search(new Criteria(), $this->context)->first();
        static::assertInstanceOf(UserRecoveryEntity::class, $userRecovery);
    }

    public function testGenerateUserRecoveryWithoutExistingUser(): void
    {
        $this->createRecovery('foo@bar.com');

        $userRecovery = $this->userRecoveryRepo->search(new Criteria(), $this->context)->first();
        static::assertNull($userRecovery);
    }

    #[DataProvider('dataProviderTestCheckHash')]
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

    /**
     * @return array<array<int, \DateInterval|string|bool>>
     */
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
        $this->createRecovery(self::VALID_EMAIL);

        static::assertInstanceOf(UserRecoveryEntity::class, $recovery = $this->userRecoveryRepo->search(new Criteria(), $this->context)->first());

        $hash = $recovery->getHash();

        $user = $this->userRepo->search(new Criteria(), $this->context)->getEntities()->first();
        static::assertInstanceOf(UserEntity::class, $user);

        $passwordBefore = $user->getPassword();

        $this->userRecoveryService->updatePassword($hash, 'newPassword', $this->context);

        $userAfter = $this->userRepo->search(new Criteria(), $this->context)->getEntities()->first();
        static::assertInstanceOf(UserEntity::class, $userAfter);

        $passwordAfter = $userAfter->getPassword();

        static::assertNotEquals($passwordBefore, $passwordAfter);
    }

    public function testGetUserByHash(): void
    {
        $this->createRecovery(self::VALID_EMAIL);

        $criteria = new Criteria();
        $criteria->setLimit(1);

        static::assertInstanceOf(UserRecoveryEntity::class, $recovery = $this->userRecoveryRepo->search(new Criteria(), $this->context)->first());

        $hash = $recovery->getHash();

        $invalid = $this->userRecoveryService->getUserByHash('invalid', $this->context);
        static::assertNull($invalid);

        $valid = $this->userRecoveryService->getUserByHash($hash, $this->context);
        static::assertInstanceOf(UserEntity::class, $valid);
        static::assertSame(self::VALID_EMAIL, $valid->getEmail());
    }

    public function testReEvaluateRules(): void
    {
        $validator = new RuleValidator();
        $this->getContainer()
            ->get('event_dispatcher')
            ->addListener(UserRecoveryRequestEvent::EVENT_NAME, $validator);

        $this->userRecoveryService->generateUserRecovery(
            self::VALID_EMAIL,
            Context::createDefaultContext()
        );

        static::assertInstanceOf(UserRecoveryRequestEvent::class, $validator->event);
        static::assertNotEmpty($validator->event->getContext()->getRuleIds());
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
    public ?UserRecoveryRequestEvent $event = null;

    public function __invoke(): void
    {
        $this->event = func_get_arg(0);
    }
}
