<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\TaxAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Test\Page\StorefrontPageTestConstants;

class CheckoutConfirmControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;
    use TaxAddToSalesChannelTestBehaviour;

    private $browser;

    private $crawler;

    public function __construct()
    {
        parent::__construct();
        $this->browser = KernelLifecycleManager::createBrowser($this->getKernel());
    }

    public function testHandleUnavailablePaymentMethod(): void
    {
        $this->createCustomer();
        $this->login();
        $this->addProductToCart();
        $this->disabledPaymentMethods();
        $this->setCheckoutConfirmCrawler();

        // getting the elements that will be tested later
        $disabledAttribute = $this->crawler->filterXPath('//*[@id="confirmFormSubmit"]')->extract(['disabled'])[0];
        $textOfCurrentPaymentMethodSelection = $this->crawler->filterXPath('//*[@class="confirm-payment-current"]')->text();
        $textOfPaymentSelectionButton = $this->crawler->filterXPath('//*[@class="col-sm-6 confirm-payment"]//*[@class="btn btn-light"]')->text();

        // trimming unnecessary white space at the start and end, then removing the white space between the words inside the string
        $textOfCurrentPaymentMethodSelection = preg_replace('/\s+/', ' ', trim($textOfCurrentPaymentMethodSelection));

        static::assertSame('Current selection: None', $textOfCurrentPaymentMethodSelection);
        static::assertSame('Choose payment', trim($textOfPaymentSelectionButton));
        static::assertSame('', $disabledAttribute);
    }

    public function testHandleUnavailablePaymentMethodViaApi(): void
    {
        $this->createCustomer();
        $this->login();
        $this->addProductToCart();
        $this->disabledPaymentMethods();
        $this->setCheckoutConfirmCrawler();

        $this->browser->request(
            'POST',
            getenv('APP_URL') . '/checkout/order',
            $this->tokenize('frontend.checkout.finish.order', ['tos' => 'on'])
        );

        static::assertEquals(404, $this->browser->getResponse()->getStatusCode());
    }

    protected function getRandomProduct(SalesChannelContext $context, ?int $stock = 1, ?bool $isCloseout = false): ProductEntity
    {
        $id = Uuid::randomHex();
        $productNumber = Uuid::randomHex();
        $productRepository = $this->getContainer()->get('product.repository');

        $data = [
            'id' => $id,
            'productNumber' => $productNumber,
            'stock' => $stock,
            'name' => StorefrontPageTestConstants::PRODUCT_NAME,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 15],
            'active' => true,
            'isCloseout' => $isCloseout,
            'categories' => [
                ['id' => Uuid::randomHex(), 'name' => 'asd'],
            ],
            'visibilities' => [
                ['salesChannelId' => $context->getSalesChannel()->getId(), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];

        $productRepository->create([$data], $context->getContext());
        $this->addTaxDataToSalesChannel($context, $data['tax']);

        /** @var SalesChannelRepositoryInterface $storefrontProductRepository */
        $storefrontProductRepository = $this->getContainer()->get('sales_channel.product.repository');
        $searchResult = $storefrontProductRepository->search(new Criteria([$id]), $context);

        return $searchResult->first();
    }

    private function createCustomer(): void
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $data = [
            [
                'id' => $customerId,
                'salesChannelId' => Defaults::SALES_CHANNEL,
                'defaultShippingAddress' => [
                    'id' => $addressId,
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Musterstraße 1',
                    'city' => 'Schöppingen',
                    'zipcode' => '12345',
                    'salutationId' => $this->getValidSalutationId(),
                    'country' => ['name' => 'Germany'],
                ],
                'defaultBillingAddressId' => $addressId,
                'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
                'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                'email' => 'foo@bar.de',
                'password' => 'password',
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'salutationId' => $this->getValidSalutationId(),
                'customerNumber' => '12345',
            ],
        ];

        $repo = $this->getContainer()->get('customer.repository');

        $repo->create($data, Context::createDefaultContext());

        $repo->search(new Criteria([$customerId]), Context::createDefaultContext())->first();
    }

    private function login(): void
    {
        $this->browser->request(
            'POST',
            getenv('APP_URL') . '/account/login',
            $this->tokenize('frontend.account.login', ['username' => 'foo@bar.de', 'password' => 'password'])
        );
    }

    private function addProductToCart(): void
    {
        $contextToken = $this->browser->getResponse()->headers->get('sw-context-token');

        $storefrontSalesChannelId = $this->getContainer()->get(Connection::class)->fetchColumn('SELECT lower(HEX(id)) FROM sales_channel WHERE type_id =:type', ['type' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_STOREFRONT)]);

        $salesChannelContext = $this->getContainer()->get(SalesChannelContextService::class)->get($storefrontSalesChannelId, $contextToken);

        $this->browser->request(
            'POST',
            getenv('APP_URL') . '/checkout/product/add-by-number',
            $this->tokenize('frontend.checkout.product.add-by-number', ['number' => $this->getRandomProduct($salesChannelContext)->getProductNumber()])
        );
    }

    private function disabledPaymentMethods(): void
    {
        $this->getContainer()->get(Connection::class)->executeUpdate('UPDATE payment_method SET active = 0');
    }

    private function setCheckoutConfirmCrawler(): void
    {
        $this->crawler = $this->browser->request('GET', getenv('APP_URL') . '/checkout/confirm');
    }
}
