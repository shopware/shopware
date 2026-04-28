<?php declare(strict_types=1);

namespace Shopware\Core\Test\Integration\Traits;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('checkout')]
trait CustomerTestTrait
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private function createCustomerContextToken(string $customerId, string $salesChannelId = TestDefaults::SALES_CHANNEL): string
    {
        $token = SalesChannelContextService::getNewToken();

        static::getContainer()->get(SalesChannelContextPersister::class)->create(
            $token,
            $salesChannelId,
            $customerId,
        );

        return $token;
    }
}
