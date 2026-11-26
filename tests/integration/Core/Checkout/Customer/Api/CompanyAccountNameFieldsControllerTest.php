<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Customer\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\TestUser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('checkout')]
class CompanyAccountNameFieldsControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function testAnAdminWhoCanOnlyViewCustomersGetsTheAnswer(): void
    {
        TestUser::createNewTestUser(static::getContainer()->get(Connection::class), ['customer:read'])
            ->authorizeBrowser($this->getBrowser());

        $this->getBrowser()->request('GET', '/api/_action/customer/company-account-name-fields');

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        static::assertArrayHasKey('contactPersonRequired', json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testAnAdminWhoCannotViewCustomersIsDenied(): void
    {
        TestUser::createNewTestUser(static::getContainer()->get(Connection::class), ['product:read'])
            ->authorizeBrowser($this->getBrowser());

        $this->getBrowser()->request('GET', '/api/_action/customer/company-account-name-fields');

        static::assertSame(Response::HTTP_FORBIDDEN, $this->getBrowser()->getResponse()->getStatusCode());
    }
}
