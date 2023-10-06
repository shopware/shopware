<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\User;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class UserCrudTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * Tests a circular reference between user and media entity where a media is
     * defined as avatar image but at the same time the user provides some "created by" media.
     *
     * This test ensures that the user can be deleted without deleting the media first
     */
    public function testCircurlaUserMediaReferences(): void
    {
        $userId = 'c3e61243802b4d268527194b4c6bad5c';

        $userRepository = $this->getContainer()->get('user.repository');

        $userRepository->create([[
            'id' => $userId,
            'username' => 'dummy',
            'password' => 'i am safe',
            'email' => 'some-guy@shopware.com',
            'firstName' => 'first',
            'lastName' => 'last',
            'active' => true,
            'admin' => false,
            'locale' => [
                'name' => 'somewhere',
                'code' => 'swh',
                'territory' => 'somewhere',
            ],
            'media' => [
                ['id' => '4785006ad6d04a8e84a1b22aaafcf29a'],
                ['id' => 'e16958a74ca74c1f8608e8c0bd136921'],
            ],
            'avatarMedia' => [
                'id' => '3832e7cc43a54a3399ab4f1f830bae60',
            ],
        ]], Context::createDefaultContext());

        $userRepository->delete([['id' => $userId]], Context::createDefaultContext());

        $user = $userRepository->search(new Criteria([$userId]), Context::createDefaultContext())->first();
        static::assertNull($user);
    }
}
