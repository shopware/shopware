<?php

declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Integration\Builder\Order\OrderBuilder;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class ApiControllerDetailTest extends TestCase
{
    use AdminApiTestBehaviour;
    use BasicTestDataBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testJsonApiResponseSingle(): void
    {
        $id = Uuid::randomHex();
        $insertData = ['id' => $id, 'name' => 'test'];

        $this->getBrowser()->jsonRequest('POST', '/api/category', $insertData);
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
        $location = $response->headers->get('Location');
        static::assertNotEmpty($location);

        static::assertIsString($location);
        $this->getBrowser()->jsonRequest('GET', $location);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $respData = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsArray($respData);
        static::assertArrayHasKey('data', $respData);
        static::assertArrayHasKey('links', $respData);
        static::assertArrayHasKey('included', $respData);

        $catData = $respData['data'];
        static::assertArrayHasKey('type', $catData);
        static::assertArrayHasKey('id', $catData);
        static::assertArrayHasKey('attributes', $catData);
        static::assertArrayHasKey('links', $catData);
        static::assertArrayHasKey('relationships', $catData);
        static::assertArrayHasKey('translations', $catData['relationships']);
        static::assertArrayHasKey('meta', $catData);
        static::assertArrayHasKey('translated', $catData['attributes']);
        static::assertArrayHasKey('name', $catData['attributes']['translated']);

        static::assertSame($id, $catData['id']);
        static::assertSame('category', $catData['type']);
        static::assertSame($insertData['name'], $catData['attributes']['name']);
        static::assertSame($insertData['name'], $catData['attributes']['translated']['name']);
    }

    public function testGetDefaultShippingAddressViaCustomer(): void
    {
        $ids = $this->createCustomer();

        $this->getBrowser()->jsonRequest('GET', '/api/customer/' . $ids->get('customer') . '/default-shipping-address');
        $response = $this->getBrowser()->getResponse();
        $content = $response->getContent();
        static::assertIsString($content);

        if (Feature::isActive('v6.8.0.0')) {
            static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $content);

            $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
            static::assertArrayHasKey('data', $decoded);
            static::assertCount(1, $decoded['data']);
            static::assertSame($ids->get('address2'), $decoded['data'][0]['id']);
        } else {
            static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode(), $content);
        }
    }

    public function testGetBillingAddressViaOrder(): void
    {
        $ids = $this->createOrder();

        $this->getBrowser()->jsonRequest('GET', '/api/order/' . $ids->get('order') . '/billing-address');
        $response = $this->getBrowser()->getResponse();
        $content = $response->getContent();
        static::assertIsString($content);

        if (Feature::isActive('v6.8.0.0')) {
            static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $content);

            $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
            static::assertArrayHasKey('data', $decoded);
            static::assertCount(1, $decoded['data']);
            static::assertSame($ids->get('billing-address'), $decoded['data'][0]['id']);
        } else {
            static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode(), $content);
        }
    }

    private function createCustomer(): IdsCollection
    {
        $ids = new IdsCollection();

        $data = [
            'id' => $ids->get('customer'),
            'number' => '1337',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'customerNumber' => '1337',
            'email' => $ids->get('email') . '@example.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'defaultBillingAddressId' => $ids->get('address'),
            'defaultShippingAddressId' => $ids->get('address2'),
            'addresses' => [
                [
                    'id' => $ids->get('address'),
                    'customerId' => $ids->get('customer'),
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
                [
                    'id' => $ids->get('address2'),
                    'customerId' => $ids->get('customer'),
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Peter',
                    'lastName' => 'Pan',
                    'street' => 'Musterstraße 10',
                    'zipcode' => '12345',
                    'city' => 'Musterstadt',
                ],
            ],
        ];

        static::getContainer()->get('customer.repository')
            ->create([$data], Context::createDefaultContext());

        return $ids;
    }

    private function createOrder(): IdsCollection
    {
        $ids = new IdsCollection();

        $data = (new OrderBuilder($ids, 'order'))
            ->add('orderDateTime', (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT))
            ->add('billingAddressId', $ids->get('billing-address'))
            ->addAddress('billing-address', [
                'id' => $ids->get('billing-address'),
                'salutationId' => $this->getValidSalutationId(),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Ebbinghoff 10',
                'zipcode' => '48624',
                'city' => 'Schöppingen',
                'countryId' => $this->getValidCountryId(),
            ])
            ->addAddress('shipping-address', [
                'id' => $ids->get('shipping-address'),
                'salutationId' => $this->getValidSalutationId(),
                'firstName' => 'Peter',
                'lastName' => 'Pan',
                'street' => 'Musterstraße 10',
                'zipcode' => '12345',
                'city' => 'Musterstadt',
                'countryId' => $this->getValidCountryId(),
            ])
            ->build();

        static::getContainer()->get('order.repository')
            ->create([$data], Context::createDefaultContext());

        return $ids;
    }
}
