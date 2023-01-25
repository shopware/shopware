<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\Test\TestDefaults;

#[Package('customer-order')]
trait CustomerTestTrait
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private function getLoggedInContextToken(string $customerId, string $salesChannelId = TestDefaults::SALES_CHANNEL): string
    {
        $token = Random::getAlphanumericString(32);
        $this->getContainer()->get(SalesChannelContextPersister::class)->save(
            $token,
            [
                'customerId' => $customerId,
                'billingAddressId' => null,
                'shippingAddressId' => null,
            ],
            $salesChannelId,
            $customerId
        );

        return $token;
    }
}
