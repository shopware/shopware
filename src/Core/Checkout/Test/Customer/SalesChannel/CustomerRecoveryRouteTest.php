<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Exception\CustomerNotFoundByHashException;
use Shopware\Core\Checkout\Customer\SalesChannel\CustomerRecoveryIsExpiredRoute;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 *
 * @group store-api
 */
#[Package('customer-order')]
class CustomerRecoveryRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use CustomerTestTrait;

    private string $hash;

    private string $hashId;

    protected function setUp(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $customerId = $this->createCustomer('shopware', $email);

        $this->hash = Random::getAlphanumericString(32);
        $this->hashId = Uuid::randomHex();

        $this->getContainer()->get('customer_recovery.repository')->create([
            [
                'id' => $this->hashId,
                'customerId' => $customerId,
                'hash' => $this->hash,
            ],
        ], Context::createDefaultContext());
    }

    public function testNotDecorated(): void
    {
        $customerRecoveryRoute = $this->getContainer()->get(CustomerRecoveryIsExpiredRoute::class);

        static::expectException(DecorationPatternException::class);
        $customerRecoveryRoute->getDecorated();
    }

    public function testGetCustomerRecoveryNotFound(): void
    {
        $customerRecoveryRoute = $this->getContainer()->get(CustomerRecoveryIsExpiredRoute::class);

        $token = Uuid::randomHex();

        $context = $this->getContainer()->get(SalesChannelContextFactory::class)->create($token, TestDefaults::SALES_CHANNEL);

        static::expectException(CustomerNotFoundByHashException::class);
        $customerRecoveryRoute->load(new RequestDataBag(['hash' => Random::getAlphanumericString(32)]), $context);
    }

    public function testGetCustomerRecoveryInvalidHash(): void
    {
        $customerRecoveryRoute = $this->getContainer()->get(CustomerRecoveryIsExpiredRoute::class);

        $token = Uuid::randomHex();

        $context = $this->getContainer()->get(SalesChannelContextFactory::class)->create($token, TestDefaults::SALES_CHANNEL);

        static::expectException(ConstraintViolationException::class);
        $customerRecoveryRoute->load(new RequestDataBag(['hash' => 'ThisIsAWrongHash']), $context);
    }

    public function testGetCustomerRecovery(): void
    {
        $customerRecoveryRoute = $this->getContainer()->get(CustomerRecoveryIsExpiredRoute::class);

        $token = Uuid::randomHex();

        $context = $this->getContainer()->get(SalesChannelContextFactory::class)->create($token, TestDefaults::SALES_CHANNEL);

        $customerRecoveryResponse = $customerRecoveryRoute->load(new RequestDataBag(['hash' => $this->hash]), $context);

        static::assertFalse($customerRecoveryResponse->isExpired());
    }

    public function testGetCustomerRecoveryExpired(): void
    {
        $customerRecoveryRoute = $this->getContainer()->get(CustomerRecoveryIsExpiredRoute::class);

        $token = Uuid::randomHex();

        $context = $this->getContainer()->get(SalesChannelContextFactory::class)->create($token, TestDefaults::SALES_CHANNEL);

        $this->getContainer()->get(Connection::class)->update(
            'customer_recovery',
            [
                'created_at' => (new \DateTime())->sub(new \DateInterval('PT3H'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
            [
                'id' => Uuid::fromHexToBytes($this->hashId),
            ]
        );

        $customerRecoveryResponse = $customerRecoveryRoute->load(new RequestDataBag(['hash' => $this->hash]), $context);

        static::assertTrue($customerRecoveryResponse->isExpired());
    }
}
