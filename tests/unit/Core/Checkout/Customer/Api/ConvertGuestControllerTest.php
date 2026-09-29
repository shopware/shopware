<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Api\ConvertGuestController;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractConvertGuestRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractSendPasswordRecoveryMailRoute;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ConvertGuestController::class)]
class ConvertGuestControllerTest extends TestCase
{
    public function testConvertWithPasswordDoesNotSendRecoveryMail(): void
    {
        $customer = $this->createCustomer();

        $repository = static::createStub(EntityRepository::class);

        $searchResult = static::createStub(EntitySearchResult::class);
        $searchResult->method('getEntities')
            ->willReturn(new CustomerCollection([$customer]));

        $repository->method('search')
            ->willReturn($searchResult);

        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')
            ->willReturn('context-token');

        $salesChannelContext = static::createStub(SalesChannelContext::class);

        $contextService = static::createMock(SalesChannelContextServiceInterface::class);
        $contextService->expects($this->once())
            ->method('get')
            ->with(static::isInstanceOf(SalesChannelContextServiceParameters::class))
            ->willReturn($salesChannelContext);

        $convertRoute = static::createMock(AbstractConvertGuestRoute::class);
        $convertRoute->expects($this->once())
            ->method('convertGuest')
            ->with(
                static::callback(function (RequestDataBag $bag) {
                    return $bag->get('password') === 'my-secret';
                }),
                $salesChannelContext,
                $customer
            );

        $recoveryRoute = static::createMock(AbstractSendPasswordRecoveryMailRoute::class);
        $recoveryRoute->expects($this->never())
            ->method('sendRecoveryMail');

        $controller = new ConvertGuestController(
            $repository,
            $contextService,
            $convertRoute,
            $recoveryRoute,
            $connection
        );

        $request = new Request(
            request: [
                'password' => 'my-secret',
            ]
        );

        $request->headers->set(
            PlatformRequest::HEADER_LANGUAGE_ID,
            'language-id'
        );

        $context = Context::createDefaultContext();

        $controller->convert(
            $request,
            $context,
            $customer->getId(),
        );
    }

    public function testCustomerNotFound(): void
    {
        $repository = static::createStub(EntityRepository::class);

        $searchResult = static::createStub(EntitySearchResult::class);
        $searchResult->method('getEntities')->willReturn(new CustomerCollection());

        $repository->method('search')->willReturn($searchResult);

        $controller = new ConvertGuestController(
            $repository,
            static::createStub(SalesChannelContextServiceInterface::class),
            static::createStub(AbstractConvertGuestRoute::class),
            static::createStub(AbstractSendPasswordRecoveryMailRoute::class),
            static::createStub(Connection::class)
        );

        $this->expectException(CustomerException::class);

        $controller->convert(
            new Request(),
            Context::createDefaultContext(),
            'customer-id'
        );
    }

    public function testConvertWithoutPasswordSendsRecoveryMail(): void
    {
        $customer = $this->createCustomer();

        $repository = static::createStub(EntityRepository::class);

        $searchResult = static::createStub(EntitySearchResult::class);
        $searchResult->method('getEntities')->willReturn(new CustomerCollection([$customer]));

        $repository->method('search')->willReturn($searchResult);

        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')->willReturn('context-token');

        $domain = new SalesChannelDomainEntity();
        $domain->setUniqueIdentifier(Uuid::randomHex());
        $domain->setLanguageId(Uuid::randomHex());
        $domain->setUrl('https://shopware.test');

        $domains = new SalesChannelDomainCollection([$domain]);
        $salesChannel = $this->createSalesChannel($domains);
        $salesChannelContext = $this->createSalesChannelContext($customer, $salesChannel);

        $contextService = static::createMock(SalesChannelContextServiceInterface::class);
        $contextService->expects($this->once())
            ->method('get')
            ->willReturn($salesChannelContext);

        $convertGuestRoute = static::createMock(AbstractConvertGuestRoute::class);
        $convertGuestRoute->expects($this->once())
            ->method('convertGuest');

        $sendPasswordRecoveryMailRoute = static::createMock(AbstractSendPasswordRecoveryMailRoute::class);
        $sendPasswordRecoveryMailRoute
            ->expects($this->once())
            ->method('sendRecoveryMail')
            ->with(
                static::callback(function (RequestDataBag $data): bool {
                    return $data->get('email') === 'test@example.com'
                        && $data->get('storefrontUrl') === 'https://shopware.test';
                }),
                static::identicalTo($salesChannelContext)
            );

        $controller = new ConvertGuestController(
            $repository,
            $contextService,
            $convertGuestRoute,
            $sendPasswordRecoveryMailRoute,
            $connection
        );

        $request = new Request();
        $request->headers->set(
            PlatformRequest::HEADER_LANGUAGE_ID,
            Uuid::randomHex()
        );

        $controller->convert(
            $request,
            Context::createDefaultContext(),
            $customer->getUniqueIdentifier()
        );
    }

    public function testConvertThrowsExceptionWhenNoSalesChannelDomainExists(): void
    {
        $customer = $this->createCustomer();
        $repository = static::createStub(EntityRepository::class);

        $searchResult = static::createStub(EntitySearchResult::class);
        $searchResult->method('getEntities')->willReturn(new CustomerCollection([$customer]));

        $repository->method('search')->willReturn($searchResult);

        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')->willReturn('context-token');

        $salesChannel = $this->createSalesChannel();
        $salesChannelContext = $this->createSalesChannelContext($customer, $salesChannel);

        $contextService = static::createStub(SalesChannelContextServiceInterface::class);
        $contextService->method('get')->willReturn($salesChannelContext);

        $convertGuestRoute = static::createMock(AbstractConvertGuestRoute::class);
        $convertGuestRoute->expects($this->once())
            ->method('convertGuest');

        $sendPasswordRecoveryMailRoute = static::createMock(AbstractSendPasswordRecoveryMailRoute::class);
        $sendPasswordRecoveryMailRoute->expects($this->never())
            ->method('sendRecoveryMail');

        $controller = new ConvertGuestController(
            $repository,
            $contextService,
            $convertGuestRoute,
            $sendPasswordRecoveryMailRoute,
            $connection
        );

        $this->expectException(CustomerException::class);

        $request = new Request();

        $controller->convert(
            $request,
            Context::createDefaultContext(),
            $customer->getUniqueIdentifier()
        );
    }

    private function createCustomer(): CustomerEntity
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setUniqueIdentifier(Uuid::randomHex());
        $customer->setSalesChannelId(Uuid::randomHex());
        $customer->setEmail('test@example.com');
        $customer->setLanguageId(Uuid::randomHex());

        return $customer;
    }

    private function createSalesChannel(?SalesChannelDomainCollection $domains = null): SalesChannelEntity
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());
        $salesChannel->setUniqueIdentifier(Uuid::randomHex());
        if ($domains) {
            $salesChannel->setDomains($domains);
        }

        return $salesChannel;
    }

    private function createSalesChannelContext(CustomerEntity $customer, SalesChannelEntity $salesChannel): SalesChannelContext
    {
        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext->method('getCustomer')->willReturn($customer);
        $salesChannelContext->method('getSalesChannel')->willReturn($salesChannel);

        return $salesChannelContext;
    }
}
