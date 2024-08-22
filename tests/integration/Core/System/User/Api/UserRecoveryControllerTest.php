<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\User\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\Log\Monolog\DoctrineSQLHandler;
use Shopware\Core\Framework\Log\Monolog\ExcludeFlowEventHandler;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\EventDispatcherBehaviour;
use Shopware\Core\Maintenance\User\Service\UserProvisioner;
use Shopware\Core\System\User\Aggregate\UserRecovery\UserRecoveryEntity;
use Shopware\Core\System\User\Recovery\UserRecoveryRequestEvent;
use Shopware\Core\System\User\Recovery\UserRecoveryService;

/**
 * @internal
 */
#[Package('services-settings')]
class UserRecoveryControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;
    use EventDispatcherBehaviour;

    private const VALID_EMAIL = UserProvisioner::USER_EMAIL_FALLBACK;

    public function testUpdateUserPassword(): void
    {
        $this->createRecovery(self::VALID_EMAIL);

        $this->getBrowser()->request(
            'PATCH',
            '/api/_action/user/user-recovery/password',
            [
                'hash' => $this->getHash(),
                'password' => 'NewPassword!',
                'passwordConfirm' => 'NewPassword!',
            ]
        );

        static::assertEquals(200, $this->getBrowser()->getResponse()->getStatusCode());
    }

    public function testUpdateUserPasswordWithInvalidHash(): void
    {
        $this->createRecovery(self::VALID_EMAIL);

        $this->getBrowser()->request(
            'PATCH',
            '/api/_action/user/user-recovery/password',
            [
                'hash' => 'invalid',
                'password' => 'NewPassword!',
                'passwordConfirm' => 'NewPassword!',
            ]
        );

        static::assertEquals(400, $this->getBrowser()->getResponse()->getStatusCode());
    }

    public function testCreateUserRecovery(): void
    {
        $logger = $this->getContainer()->get('monolog.logger.business_events');
        $handlers = $logger->getHandlers();
        $logger->setHandlers([
            new ExcludeFlowEventHandler($this->getContainer()->get(DoctrineSQLHandler::class), [
                UserRecoveryRequestEvent::EVENT_NAME,
            ]),
        ]);

        $dispatchedEvent = null;

        $this->addEventListener(
            $this->getContainer()->get('event_dispatcher'),
            UserRecoveryRequestEvent::EVENT_NAME,
            function (UserRecoveryRequestEvent $event) use (&$dispatchedEvent): void {
                $dispatchedEvent = $event;
            },
        );
        $this->getBrowser()->request(
            'POST',
            '/api/_action/user/user-recovery',
            [
                'email' => self::VALID_EMAIL,
            ]
        );

        static::assertEquals(200, $this->getBrowser()->getResponse()->getStatusCode());

        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('user.email', self::VALID_EMAIL));

        $userRecovery = $this->getContainer()->get('user_recovery.repository')->search(
            $criteria,
            Context::createDefaultContext()
        )->first();

        static::assertNotNull($userRecovery);
        static::assertNotNull($dispatchedEvent);

        // excluded events and its mail events should not be logged
        $originalEvent = $dispatchedEvent->getName();
        $logCriteria = new Criteria();
        $logCriteria->addFilter(new OrFilter([
            new EqualsFilter('message', $originalEvent),
            new EqualsFilter('context.additionalData.eventName', $originalEvent),
        ]));

        $logEntries = $this->getContainer()->get('log_entry.repository')->search(
            $logCriteria,
            Context::createDefaultContext()
        );

        static::assertCount(0, $logEntries);

        $this->resetEventDispatcher();
        $logger->setHandlers($handlers);
    }

    private function createRecovery(string $email): void
    {
        $this->getContainer()->get(UserRecoveryService::class)->generateUserRecovery(
            $email,
            Context::createDefaultContext()
        );
    }

    private function getHash(): string
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);

        static::assertInstanceOf(UserRecoveryEntity::class, $recovery = $this->getContainer()->get('user_recovery.repository')->search(
            $criteria,
            Context::createDefaultContext()
        )->first());

        return $recovery->getHash();
    }
}
