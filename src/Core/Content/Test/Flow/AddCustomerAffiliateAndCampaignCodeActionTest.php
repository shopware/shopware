<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Flow;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Content\Flow\Dispatching\Action\AddCustomerAffiliateAndCampaignCodeAction;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('business-ops')]
class AddCustomerAffiliateAndCampaignCodeActionTest extends TestCase
{
    use OrderActionTrait;
    use CacheTestBehaviour;

    private EntityRepository $flowRepository;

    protected function setUp(): void
    {
        $this->flowRepository = $this->getContainer()->get('flow.repository');

        $this->customerRepository = $this->getContainer()->get('customer.repository');

        $this->ids = new TestDataCollection();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $this->ids->create('token'));
    }

    /**
     * @param array<string, mixed> $existedData
     * @param array<string, mixed> $updateData
     * @param array<string, mixed> $expectData
     *
     * @dataProvider createDataProvider
     */
    public function testAddAffiliateAndCampaignCodeForCustomer(array $existedData, array $updateData, array $expectData): void
    {
        $email = 'thuy@gmail.com';
        $password = '12345678';
        $this->prepareCustomer($password, $email, $existedData);

        $sequenceId = Uuid::randomHex();
        $this->flowRepository->create([[
            'name' => 'Customer login',
            'eventName' => CustomerLoginEvent::EVENT_NAME,
            'priority' => 1,
            'active' => true,
            'sequences' => [
                [
                    'id' => $sequenceId,
                    'parentId' => null,
                    'ruleId' => null,
                    'actionName' => AddCustomerAffiliateAndCampaignCodeAction::getName(),
                    'position' => 1,
                    'config' => $updateData,
                ],
            ],
        ]], Context::createDefaultContext());

        $this->login($email, $password);

        static::assertNotNull($this->customerRepository);
        /** @var CustomerEntity $customer */
        $customer = $this->customerRepository->search(new Criteria([$this->ids->get('customer')]), Context::createDefaultContext())->first();

        static::assertEquals($customer->getAffiliateCode(), $expectData['affiliateCode']);
        static::assertEquals($customer->getCampaignCode(), $expectData['campaignCode']);
    }

    /**
     * @return array<int, mixed>
     */
    public static function createDataProvider(): array
    {
        return [
            // existed data / update data / expect data
            [
                [],
                [
                    'affiliateCode' => ['value' => '11111', 'upsert' => false],
                    'campaignCode' => ['value' => '22222', 'upsert' => false],
                ],
                ['affiliateCode' => '11111', 'campaignCode' => '22222'],
            ],
            [
                ['affiliateCode' => '11111', 'campaignCode' => '22222'],
                [
                    'affiliateCode' => ['value' => '33333', 'upsert' => false],
                    'campaignCode' => ['value' => '33333', 'upsert' => false],
                ],
                ['affiliateCode' => '11111', 'campaignCode' => '22222'],
            ],
            [
                ['affiliateCode' => '11111', 'campaignCode' => '22222'],
                [
                    'affiliateCode' => ['value' => '33333', 'upsert' => false],
                    'campaignCode' => ['value' => '33333', 'upsert' => true],
                ],
                ['affiliateCode' => '11111', 'campaignCode' => '33333'],
            ],
            [
                ['affiliateCode' => '11111', 'campaignCode' => '22222'],
                [
                    'affiliateCode' => ['value' => '33333', 'upsert' => true],
                    'campaignCode' => ['value' => '33333', 'upsert' => true],
                ],
                ['affiliateCode' => '33333', 'campaignCode' => '33333'],
            ],
        ];
    }
}
