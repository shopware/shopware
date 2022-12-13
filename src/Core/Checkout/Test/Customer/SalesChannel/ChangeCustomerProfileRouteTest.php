<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\ChangeCustomerProfileRoute;
use Shopware\Core\Checkout\Customer\Validation\CustomerValidationFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiCustomFieldMapper;
use Shopware\Core\System\SalesChannel\SuccessResponse;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @package customer-order
 *
 * @internal
 *
 * @covers \Shopware\Core\Checkout\Customer\SalesChannel\ChangeCustomerProfileRoute
 */
class ChangeCustomerProfileRouteTest extends TestCase
{
    public function testCustomFieldsGetPassed(): void
    {
        $customFields = new RequestDataBag(['test1' => '1', 'test2' => '2']);

        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository
            ->method('update')
            ->with([
                ['id' => 'customer1', 'company' => '', 'customFields' => ['test1' => '1']],
            ]);

        $storeApiCustomFieldMapper = $this->createMock(StoreApiCustomFieldMapper::class);
        $storeApiCustomFieldMapper
            ->expects(static::once())
            ->method('map')
            ->with('customer', $customFields)
            ->willReturn(['test1' => '1']);

        $change = new ChangeCustomerProfileRoute(
            $customerRepository,
            new EventDispatcher(),
            $this->createMock(DataValidator::class),
            $this->createMock(CustomerValidationFactory::class),
            $storeApiCustomFieldMapper
        );

        $customer = new CustomerEntity();
        $customer->setId('customer1');
        $data = new RequestDataBag([
            'customFields' => $customFields,
        ]);
        $response = $change->change($data, $this->createMock(SalesChannelContext::class), $customer);
        static::assertInstanceOf(SuccessResponse::class, $response);
    }
}
