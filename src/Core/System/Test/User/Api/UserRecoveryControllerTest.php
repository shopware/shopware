<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\User\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\System\User\Recovery\UserRecoveryService;

class UserRecoveryControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    private const VALID_EMAIL = 'info@shopware.com';

    public function testUpdateUserPassword(): void
    {
        $this->createRecocery(static::VALID_EMAIL);

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
        $this->createRecocery(static::VALID_EMAIL);

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

    private function createRecocery(string $email): void
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

        return $this->getContainer()->get('user_recovery.repository')->search(
            $criteria,
            Context::createDefaultContext()
        )->first()->getHash();
    }
}
