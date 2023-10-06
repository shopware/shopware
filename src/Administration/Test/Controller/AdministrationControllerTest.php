<?php declare(strict_types=1);

namespace Shopware\Administration\Test\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Integration\PaymentHandler\SyncTestPaymentHandler;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
class AdministrationControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    private Connection $connection;

    private EntityRepository $customerRepository;

    protected function setup(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $newLanguageId = $this->insertOtherLanguage();
        $this->createSearchConfigFieldForNewLanguage($newLanguageId);

        $this->customerRepository = $this->getContainer()->get('customer.repository');
    }

    public function testSnippetRoute(): void
    {
        $this->getBrowser()->request('GET', '/api/_admin/snippets?locale=de-DE');
        static::assertEquals(200, $this->getBrowser()->getResponse()->getStatusCode());
        $content = $this->getBrowser()->getResponse()->getContent();
        static::assertNotFalse($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('de-DE', $response);
        static::assertArrayHasKey('en-GB', $response);
    }

    public function testResetExcludedSearchTermIncorrectLanguageId(): void
    {
        $this->getBrowser()->setServerParameter('HTTP_sw-language-id', Uuid::randomHex());
        $this->getBrowser()->request('POST', '/api/_admin/reset-excluded-search-term');

        $response = $this->getBrowser()->getResponse();

        static::assertEquals(412, $response->getStatusCode());
    }

    public function testValidateEmailSuccess(): void
    {
        $browser = $this->createClient();
        $this->createCustomer(['email' => 'foo@bar.de']);

        $browser->request(
            'POST',
            '/api/_admin/check-customer-email-valid',
            [
                'id' => Uuid::randomHex(),
                'email' => 'foo1@bar.de',
                'boundSalesChannelId' => null,
            ]
        );

        $content = $this->getBrowser()->getResponse()->getContent();
        static::assertNotFalse($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertEquals(200, $browser->getResponse()->getStatusCode());
        static::assertArrayHasKey('isValid', $response);
    }

    public function testValidateEmailFail(): void
    {
        $email = 'foo@bar.de';
        $browser = $this->createClient();
        $this->createCustomer(['email' => 'foo@bar.de']);

        $browser->request(
            'POST',
            '/api/_admin/check-customer-email-valid',
            [
                'id' => Uuid::randomHex(),
                'email' => $email,
                'boundSalesChannelId' => null,
            ]
        );

        $content = $this->getBrowser()->getResponse()->getContent();
        static::assertNotFalse($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertEquals(400, $browser->getResponse()->getStatusCode());
        static::assertSame('The email address ' . $email . ' is already in use', $response['errors'][0]['detail']);
    }

    public function testValidateEmailSuccessWithSameCustomerDifferentSalesChannel(): void
    {
        $this->setCustomerBoundToSalesChannels(true);
        $newSalesChannel = $this->createSalesChannel();

        $browser = $this->createClient();
        $email = 'foo@bar.de';
        $this->createCustomer(['email' => 'foo@bar.de', 'boundSalesChannelId' => TestDefaults::SALES_CHANNEL]);

        $browser->request(
            'POST',
            '/api/_admin/check-customer-email-valid',
            [
                'id' => Uuid::randomHex(),
                'email' => $email,
                'boundSalesChannelId' => $newSalesChannel['id'],
            ]
        );

        $content = $this->getBrowser()->getResponse()->getContent();
        static::assertNotFalse($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertEquals(200, $browser->getResponse()->getStatusCode());
        static::assertArrayHasKey('isValid', $response);
    }

    public function testValidateEmailFailWithSameCustomerSameSalesChannel(): void
    {
        $this->setCustomerBoundToSalesChannels(true);
        $email = 'foo@bar.de';
        $browser = $this->createClient();
        $this->createCustomer(['email' => 'foo@bar.de', 'boundSalesChannelId' => TestDefaults::SALES_CHANNEL]);

        $browser->request(
            'POST',
            '/api/_admin/check-customer-email-valid',
            [
                'id' => Uuid::randomHex(),
                'email' => $email,
                'boundSalesChannelId' => TestDefaults::SALES_CHANNEL,
            ]
        );

        $content = $this->getBrowser()->getResponse()->getContent();
        static::assertNotFalse($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertEquals(400, $browser->getResponse()->getStatusCode());
        static::assertSame('The email address ' . $email . ' is already in use in the Sales Channel Headless', $response['errors'][0]['detail']);
    }

    public function testValidateEmailFailWithSameCustomerIsAlreadyExistsInAllSalesChannel(): void
    {
        $this->setCustomerBoundToSalesChannels(true);
        $email = 'foo@bar.de';
        $browser = $this->createClient();
        $this->createCustomer(['email' => $email]);

        $browser->request(
            'POST',
            '/api/_admin/check-customer-email-valid',
            [
                'id' => Uuid::randomHex(),
                'email' => $email,
                'boundSalesChannelId' => TestDefaults::SALES_CHANNEL,
            ]
        );

        $content = $this->getBrowser()->getResponse()->getContent();
        static::assertNotFalse($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertEquals(400, $browser->getResponse()->getStatusCode());
        static::assertSame('The email address ' . $email . ' is already in use', $response['errors'][0]['detail']);
    }

    public function testPreviewSanitizedHtml(): void
    {
        $html = '<img alt="" src="#" /><script type="text/javascript"></script><div>test</div>';
        $browser = $this->createClient();

        $browser->request(
            'POST',
            '/api/_admin/sanitize-html',
            [
                'html' => $html,
                'field' => 'product_translation.description',
            ]
        );

        $content = $this->getBrowser()->getResponse()->getContent();

        static::assertNotFalse($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertEquals(200, $browser->getResponse()->getStatusCode());
        static::assertSame('<img alt="" src="#" /><div>test</div>', $response['preview']);

        $browser->request(
            'POST',
            '/api/_admin/sanitize-html',
            [
                'html' => $html,
                'field' => 'mail_template_translation.contentHtml',
            ]
        );

        $content = $this->getBrowser()->getResponse()->getContent();

        static::assertNotFalse($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertEquals(200, $browser->getResponse()->getStatusCode());
        static::assertSame($html, $response['preview']);
    }

    /**
     * @param array<string, string|bool|int|float|null> $overrideData
     */
    private function createCustomer(array $overrideData): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $this->customerRepository->create([
            array_merge([
                'id' => $customerId,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'defaultShippingAddress' => [
                    'id' => $addressId,
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Musterstraße 1',
                    'city' => 'Schöppingen',
                    'zipcode' => '12345',
                    'salutationId' => $this->getValidSalutationId(),
                    'countryId' => $this->getValidCountryId(),
                ],
                'defaultBillingAddressId' => $addressId,
                'defaultPaymentMethod' => [
                    'name' => 'Invoice',
                    'active' => true,
                    'description' => 'Default payment method',
                    'handlerIdentifier' => SyncTestPaymentHandler::class,
                    'availabilityRule' => [
                        'id' => Uuid::randomHex(),
                        'name' => 'true',
                        'priority' => 0,
                        'conditions' => [
                            [
                                'type' => 'cartCartAmount',
                                'value' => [
                                    'operator' => '>=',
                                    'amount' => 0,
                                ],
                            ],
                        ],
                    ],
                    'salesChannels' => [
                        [
                            'id' => TestDefaults::SALES_CHANNEL,
                        ],
                    ],
                ],
                'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                'email' => 'random@mail.com',
                'password' => TestDefaults::HASHED_PASSWORD,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'guest' => false,
                'salutationId' => $this->getValidSalutationId(),
                'customerNumber' => '12345',
            ], $overrideData),
        ], Context::createDefaultContext());

        return $customerId;
    }

    private function insertOtherLanguage(): string
    {
        $langId = $this->connection->executeQuery(
            'SELECT id FROM `language` WHERE `name` = :langName',
            [
                'langName' => 'Vietnamese',
            ]
        )->fetchFirstColumn();

        $localeId = $this->connection->executeQuery(
            'SELECT id FROM `locale` WHERE `code` = :code',
            [
                'code' => 'vi-VN',
            ]
        )->fetchFirstColumn();

        if ($langId) {
            return $langId[0];
        }

        $newLanguageId = Uuid::randomBytes();
        $statement = $this->connection->prepare('INSERT INTO `language` (`id`, `name`, `locale_id`, `translation_code_id`, `created_at`)
            VALUES (?, ?, ?, ?, ?)');
        $statement->executeStatement([$newLanguageId, 'Vietnamese', $localeId[0], $localeId[0], '2021-04-01 04:41:12.045']);

        return $newLanguageId;
    }

    private function createSearchConfigFieldForNewLanguage(string $newLanguageId): void
    {
        $configId = $this->connection->executeQuery(
            'SELECT id FROM `product_search_config` WHERE `language_id` = :languageId',
            [
                'languageId' => $newLanguageId,
            ]
        )->fetchFirstColumn();

        if (!$configId) {
            $newConfigId = Uuid::randomBytes();
            $statement = $this->connection->prepare('INSERT INTO `product_search_config` (`id`, `language_id`, `and_logic`, `min_search_length`, `created_at`)
                VALUES (?, ?, ?, ?, ?)');
            $statement->executeStatement([$newConfigId, $newLanguageId, 0, 2, '2021-04-01 04:41:12.045']);
        }
    }

    private function setCustomerBoundToSalesChannels(bool $value): void
    {
        $this->getContainer()
            ->get(SystemConfigService::class)
            ->set('core.systemWideLoginRegistration.isCustomerBoundToSalesChannel', $value);
    }

    /**
     * @param array<string, int|float|string|bool|null> $salesChannelOverride
     *
     * @return array<string, array<int, array<string, string|null>>|bool|float|int|string|null>
     */
    private function createSalesChannel(array $salesChannelOverride = []): array
    {
        /** @var EntityRepository $salesChannelRepository */
        $salesChannelRepository = $this->getContainer()->get('sales_channel.repository');
        $paymentMethod = $this->getAvailablePaymentMethod();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('domains.url', 'http://localhost'));
        $salesChannelIds = $salesChannelRepository->searchIds($criteria, Context::createDefaultContext());

        if (!isset($salesChannelOverride['domains']) && $salesChannelIds->firstId() !== null) {
            $salesChannelRepository->delete([['id' => $salesChannelIds->firstId()]], Context::createDefaultContext());
        }

        $salesChannel = array_merge([
            'id' => $salesChannelOverride['id'] ?? Uuid::randomHex(),
            'typeId' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
            'name' => 'new sales channel',
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
            'currencyId' => Defaults::CURRENCY,
            'paymentMethodId' => $paymentMethod->getId(),
            'paymentMethods' => [['id' => $paymentMethod->getId()]],
            'shippingMethodId' => $this->getAvailableShippingMethod()->getId(),
            'navigationCategoryId' => $this->getValidCategoryId(),
            'countryId' => $this->getValidCountryId(null),
            'currencies' => [['id' => Defaults::CURRENCY]],
            'languages' => $salesChannelOverride['languages'] ?? [['id' => Defaults::LANGUAGE_SYSTEM]],
            'customerGroupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://localhost',
                ],
            ],
            'countries' => [['id' => $this->getValidCountryId(null)]],
        ], $salesChannelOverride);

        $salesChannelRepository->upsert([$salesChannel], Context::createDefaultContext());

        return $salesChannel;
    }
}
