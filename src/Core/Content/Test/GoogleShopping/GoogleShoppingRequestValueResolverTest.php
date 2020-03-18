<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\GoogleShopping;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\GoogleShopping\Client\GoogleShoppingClient;
use Shopware\Core\Content\GoogleShopping\DataAbstractionLayer\GoogleAccountCredential;
use Shopware\Core\Content\GoogleShopping\Exception\GoogleAuthenticationException;
use Shopware\Core\Content\GoogleShopping\Exception\SalesChannelIsNotGoogleShoppingTypeException;
use Shopware\Core\Content\GoogleShopping\GoogleShoppingRequest;
use Shopware\Core\Content\GoogleShopping\GoogleShoppingRequestValueResolver;
use Shopware\Core\Content\GoogleShopping\Service\GoogleShoppingAccount;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Exception\InvalidSalesChannelIdException;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use function Flag\skipTestNext6050;

class GoogleShoppingRequestValueResolverTest extends TestCase
{
    use IntegrationTestBehaviour;
    use GoogleShoppingIntegration;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|GoogleShoppingClient
     */
    private $googleShoppingClient;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|GoogleShoppingAccount
     */
    private $googleShoppingAccount;

    /**
     * @var EntityRepositoryInterface|null
     */
    private $salesChannelRepository;

    protected function setUp(): void
    {
        skipTestNext6050($this);
        $this->context = Context::createDefaultContext();
        $this->googleShoppingClient = $this->createMock(GoogleShoppingClient::class);
        $this->googleShoppingAccount = $this->getContainer()->get(GoogleShoppingAccount::class);
        $this->salesChannelRepository = $this->getContainer()->get('sales_channel.repository');
    }

    public function testResolveFailWithInvalidSalesChannelType(): void
    {
        $this->expectException(SalesChannelIsNotGoogleShoppingTypeException::class);
        $request = new Request();
        $request->attributes->set('salesChannelId', $this->createStoreFrontSaleChannel());
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $this->context);

        $resolver = new GoogleShoppingRequestValueResolver(
            $this->salesChannelRepository,
            $this->googleShoppingClient,
            $this->googleShoppingAccount
        );

        iterator_to_array($resolver->resolve($request, $this->createMock(ArgumentMetadata::class)));
    }

    public function testResolveFailWithInvalidSalesChannelId(): void
    {
        $this->expectException(InvalidSalesChannelIdException::class);
        $request = new Request();
        $request->attributes->set('salesChannelId', Uuid::randomHex());
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $this->context);

        $resolver = new GoogleShoppingRequestValueResolver(
            $this->salesChannelRepository,
            $this->googleShoppingClient,
            $this->googleShoppingAccount
        );

        iterator_to_array($resolver->resolve($request, $this->createMock(ArgumentMetadata::class)));
    }

    public function testResolveSuccess(): void
    {
        $salesChannelId = $this->createSalesChannelGoogleShopping();
        $request = new Request();
        $request->attributes->set('salesChannelId', $salesChannelId);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $this->context);

        $googleAccount = $this->createGoogleShoppingAccount(Uuid::randomHex(), $salesChannelId);

        $resolver = new GoogleShoppingRequestValueResolver(
            $this->salesChannelRepository,
            $this->googleShoppingClient,
            $this->googleShoppingAccount
        );

        $result = $resolver->resolve($request, $this->createMock(ArgumentMetadata::class));
        list($googleShoppingRequest) = iterator_to_array($result);

        static::assertNotEmpty($googleShoppingRequest);
        static::assertInstanceOf(GoogleShoppingRequest::class, $googleShoppingRequest);
        static::assertEquals($this->context, $googleShoppingRequest->getContext());
        static::assertEquals($salesChannelId, $googleShoppingRequest->getSalesChannel()->getId());
        static::assertEquals($googleAccount['id'], $googleShoppingRequest->getGoogleShoppingAccount()->getId());
    }

    public function testGoogleCredentialGetRefreshedWhenExpiredFail(): void
    {
        $exception = new GoogleAuthenticationException('INVALID REFRESH TOKEN', 'Please provide valid refresh token');
        $this->expectException(GoogleAuthenticationException::class);
        $this->expectExceptionMessage($exception->getMessage());
        $this->expectExceptionCode($exception->getCode());

        $salesChannelId = $this->createSalesChannelGoogleShopping();

        $this->createGoogleShoppingAccount(Uuid::randomHex(), $salesChannelId);

        $mockClient = $this->getMockBuilder(GoogleShoppingClient::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockClient
            ->expects(static::once())
            ->method('isAccessTokenExpired')
            ->willReturn(true);

        $mockClient
            ->expects(static::once())
            ->method('fetchAccessTokenWithRefreshToken')
            ->willReturn([
                'error' => $exception->getErrorCode(),
                'error_description' => $exception->getMessage(),
            ]);

        $request = new Request();
        $request->attributes->set('salesChannelId', $salesChannelId);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $this->context);

        $resolver = new GoogleShoppingRequestValueResolver(
            $this->salesChannelRepository,
            $mockClient,
            $this->googleShoppingAccount
        );

        iterator_to_array($resolver->resolve($request, $this->createMock(ArgumentMetadata::class)));
    }

    public function testGoogleCredentialGetRefreshedWhenExpired(): void
    {
        $salesChannelId = $this->createSalesChannelGoogleShopping();

        $googleAccount = $this->createGoogleShoppingAccount(Uuid::randomHex(), $salesChannelId);

        $mockClient = $this->getMockBuilder(GoogleShoppingClient::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockClient
            ->expects(static::once())
            ->method('isAccessTokenExpired')
            ->willReturn(true);

        $expireCredential = $googleAccount['credential'];

        $refreshedCredential = [
            'access_token' => 'new access token',
            'refresh_token' => 'new refresh token',
            'created' => 1581234,
            'id_token' => 'GOOGLE.' . base64_encode(json_encode([
                'name' => 'Jane Doe', 'email' => 'jane.doe@example.com',
            ])) . '.ID_TOKEN',
            'scope' => 'https://www.googleapis.com/auth/content https://www.googleapis.com/auth/adwords',
            'expires_in' => 3599,
        ];

        $mockClient
            ->expects(static::once())
            ->method('fetchAccessTokenWithRefreshToken')
            ->willReturn($refreshedCredential);

        $request = new Request();
        $request->attributes->set('salesChannelId', $salesChannelId);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $this->context);

        $resolver = new GoogleShoppingRequestValueResolver(
            $this->salesChannelRepository,
            $mockClient,
            $this->googleShoppingAccount
        );

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));
        $googleShoppingAccountRepository = $this->getContainer()->get('google_shopping_account.repository');

        $account = $googleShoppingAccountRepository->search($criteria, $this->context);

        static::assertEquals(new GoogleAccountCredential($expireCredential), $account->first()->getCredential());

        iterator_to_array($resolver->resolve($request, $this->createMock(ArgumentMetadata::class)));

        $account = $googleShoppingAccountRepository->search($criteria, $this->context);

        static::assertEquals(new GoogleAccountCredential($refreshedCredential), $account->first()->getCredential());
    }

    private function createStoreFrontSaleChannel()
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'navigation' => ['name' => 'test'],
            'typeId' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'currencyId' => Defaults::CURRENCY,
            'currencyVersionId' => Defaults::LIVE_VERSION,
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'paymentMethodVersionId' => Defaults::LIVE_VERSION,
            'shippingMethodId' => $this->getValidShippingMethodId(),
            'shippingMethodVersionId' => Defaults::LIVE_VERSION,
            'navigationCategoryId' => $this->getValidCategoryId(),
            'navigationCategoryVersionId' => Defaults::LIVE_VERSION,
            'countryId' => $this->getValidCountryId(),
            'countryVersionId' => Defaults::LIVE_VERSION,
            'currencies' => [['id' => Defaults::CURRENCY]],
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
            'shippingMethods' => [['id' => $this->getValidShippingMethodId()]],
            'paymentMethods' => [['id' => $this->getValidPaymentMethodId()]],
            'countries' => [['id' => $this->getValidCountryId()]],
            'name' => 'first sales-channel',
            'customerGroupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
        ];

        $this->salesChannelRepository->create([$data], $this->context);

        return $id;
    }
}
