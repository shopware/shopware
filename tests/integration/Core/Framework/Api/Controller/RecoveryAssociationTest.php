<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\FrameworkException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\User\Aggregate\UserRecovery\UserRecoveryEntity;
use Shopware\Core\Test\Integration\Builder\Customer\CustomerBuilder;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class RecoveryAssociationTest extends TestCase
{
    use AdminApiTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testUserRecoveryCannotBeLoadedThroughAssociation(): void
    {
        $context = Context::createDefaultContext();
        $id = Uuid::randomHex();
        $localeId = static::getContainer()->get(Connection::class)->fetchOne('SELECT LOWER(HEX(id)) FROM locale LIMIT 1');
        static::getContainer()->get('user.repository')->create([[
            'id' => $id,
            'username' => $id,
            'email' => $id . '@example.com',
            'firstName' => 'Recovery',
            'lastName' => 'Test',
            'password' => 'shopware',
            'localeId' => $localeId,
        ]], $context);

        $recoveryId = Uuid::randomHex();
        $repository = static::getContainer()->get('user_recovery.repository');
        $repository->create([[
            'id' => $recoveryId,
            'userId' => $id,
            'hash' => Uuid::randomHex(),
        ]], $context);

        $criteria = (new Criteria([$recoveryId]))->addAssociation('user');
        $recovery = $repository->search($criteria, $context)->getEntities()->first();
        static::assertInstanceOf(UserRecoveryEntity::class, $recovery);
        static::assertNotNull($recovery->getUser());
        static::assertSame($id, $recovery->getUser()->getId());

        $browser = $this->getBrowser();
        $browser->jsonRequest('GET', '/api/user/' . $id, [], ['HTTP_ACCEPT' => 'application/vnd.api+json']);
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayNotHasKey('recoveryUser', $content['data']['relationships']);

        $browser->jsonRequest('POST', '/api/search/user', [
            'ids' => [$id],
            'associations' => ['recoveryUser' => []],
        ]);
        $response = $browser->getResponse();
        $exception = FrameworkException::associationNotFound('recoveryUser');
        static::assertSame($exception->getStatusCode(), $response->getStatusCode(), (string) $response->getContent());
        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame($exception->getErrorCode(), $content['errors'][0]['code']);
    }

    public function testCustomerRecoveryCannotBeLoadedThroughAssociation(): void
    {
        $context = Context::createDefaultContext();
        $customer = new CustomerBuilder(new IdsCollection(), Uuid::randomHex());
        $id = $customer->id;
        static::getContainer()->get('customer.repository')->create([$customer->build()], $context);

        $recoveryId = Uuid::randomHex();
        $repository = static::getContainer()->get('customer_recovery.repository');
        $repository->create([[
            'id' => $recoveryId,
            'customerId' => $id,
            'hash' => Uuid::randomHex(),
        ]], $context);

        $criteria = (new Criteria([$recoveryId]))->addAssociation('customer');
        $recovery = $repository->search($criteria, $context)->getEntities()->first();
        static::assertInstanceOf(CustomerRecoveryEntity::class, $recovery);
        static::assertNotNull($recovery->getCustomer());
        static::assertSame($id, $recovery->getCustomer()->getId());

        $browser = $this->getBrowser();
        $browser->jsonRequest('GET', '/api/customer/' . $id, [], ['HTTP_ACCEPT' => 'application/vnd.api+json']);
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayNotHasKey('recoveryCustomer', $content['data']['relationships']);

        $browser->jsonRequest('POST', '/api/search/customer', [
            'ids' => [$id],
            'associations' => ['recoveryCustomer' => []],
        ]);
        $response = $browser->getResponse();
        $exception = FrameworkException::associationNotFound('recoveryCustomer');
        static::assertSame($exception->getStatusCode(), $response->getStatusCode(), (string) $response->getContent());
        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame($exception->getErrorCode(), $content['errors'][0]['code']);
    }
}
