<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\MailTemplate\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeCollection;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeEntity;
use Shopware\Core\Content\MailTemplate\MailTemplateCollection;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Serializer\StructNormalizer;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;

/**
 * @internal
 */
#[Package('after-sales')]
class MailActionControllerTest extends TestCase
{
    use AdminApiTestBehaviour;
    use IntegrationTestBehaviour;

    public function testSendSuccess(): void
    {
        $context = Context::createDefaultContext();

        $customerId = $this->createCustomer($context);
        $orderId = $this->createOrder($customerId, $context);

        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('orderCustomer');
        $order = static::getContainer()->get('order.repository')->search($criteria, $context)->get($orderId);
        static::assertInstanceOf(OrderEntity::class, $order);

        $documentId = $this->createDocumentWithFile($orderId, $context);
        $documentIds = [$documentId];

        $criteria = new Criteria();
        $criteria->setLimit(1);
        /** @var ?MailTemplateEntity $mailTemplate */
        $mailTemplate = static::getContainer()
            ->get('mail_template.repository')
            ->search($criteria, $context)
            ->first();
        static::assertInstanceOf(MailTemplateEntity::class, $mailTemplate);

        $criteria = new Criteria([TestDefaults::SALES_CHANNEL]);
        $criteria->setLimit(1);
        $salesChannel = static::getContainer()
            ->get('sales_channel.repository')
            ->search($criteria, $context)
            ->first();
        static::assertInstanceOf(SalesChannelEntity::class, $salesChannel);

        $entityEncoder = new JsonEntityEncoder(
            new Serializer([new StructNormalizer()], [new JsonEncoder()])
        );
        $orderDefinition = static::getContainer()->get(OrderDefinition::class);
        $orderDecode = $entityEncoder->encode(new Criteria(), $orderDefinition, $order, '/api');
        array_walk_recursive($orderDecode, static function (&$value): void {
            if ($value instanceof \stdClass) {
                $value = json_decode((string) json_encode($value), true, 512, \JSON_THROW_ON_ERROR);
            }
        });

        $salesChannelDefinition = static::getContainer()->get(SalesChannelDefinition::class);
        $salesChannelDecode = $entityEncoder->encode(new Criteria(), $salesChannelDefinition, $salesChannel, '/api');
        array_walk_recursive($salesChannelDecode, static function (&$value): void {
            if ($value instanceof \stdClass) {
                $value = json_decode((string) json_encode($value), true, 512, \JSON_THROW_ON_ERROR);
            }
        });

        $this->getBrowser()
            ->request(
                'POST',
                '/api/_action/mail-template/send',
                [
                    'contentHtml' => $mailTemplate->getContentHtml(),
                    'contentPlain' => $mailTemplate->getContentPlain(),
                    'mailTemplateData' => [
                        'order' => $orderDecode,
                        'salesChannel' => $salesChannelDecode,
                    ],
                    'documentIds' => $documentIds,
                    'recipients' => ['d.dinh@shopware.com' => 'Duy'],
                    'salesChannelId' => $salesChannel->getId(),
                    'senderName' => $salesChannel->getName(),
                    'subject' => 'New document for your order',
                    'testMode' => false,
                ],
            );

        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());
        $response = json_decode((string) $this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsArray($response);
        static::assertArrayHasKey('size', $response);
    }

    public function testPreviewSuccess(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = $this->createSimpleMailTemplate($context);

        $this->getBrowser()->request(
            'POST',
            '/api/_action/mail-template/preview',
            [
                'mailTemplateId' => $mailTemplate->getId(),
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'includeHeaderFooter' => true,
                'templateData' => [
                    'customName' => 'Shopware',
                ],
            ],
        );

        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());

        $response = json_decode((string) $this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsArray($response);
        static::assertSame('success', $response['subject']['type']);
        static::assertSame('Hello Shopware', $response['subject']['content']);
        static::assertSame('success', $response['contentHtml']['type']);
        static::assertStringContainsString('Shopware', $response['contentHtml']['content']);
    }

    public function testGetDataAndSendSuccess(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = $this->createSimpleMailTemplate($context);

        $this->getBrowser()->request(
            'POST',
            '/api/_action/mail-template/get-data-and-send',
            [
                'mailTemplateId' => $mailTemplate->getId(),
                'templateData' => [
                    'customName' => 'Shopware',
                ],
                'recipients' => ['d.dinh@shopware.com' => 'Duy'],
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'testMode' => false,
            ],
        );

        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());

        $response = json_decode((string) $this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsArray($response);
        static::assertArrayHasKey('size', $response);
        static::assertGreaterThan(0, $response['size']);
    }

    public function testSimulateSuccess(): void
    {
        $this->getBrowser()->request(
            'POST',
            '/api/_action/mail-template/simulate',
            [
                'templateParts' => [
                    'contentHtml' => '<p>{{ order.id }}</p>',
                ],
                'eventName' => 'checkout.order.placed',
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
            ],
        );

        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());

        $response = json_decode((string) $this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsArray($response);
        static::assertSame('success', $response['contentHtml']['type']);
        static::assertNotSame('', $response['contentHtml']['content']);
    }

    public function testAvailableVariablesSuccess(): void
    {
        $this->getBrowser()->request(
            'POST',
            '/api/_action/mail-template/available-variables',
            [
                'eventName' => 'checkout.order.placed',
                'parentVariablePath' => 'order',
            ],
        );

        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());

        $response = json_decode((string) $this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsArray($response);
        static::assertContains('lineItems', array_column($response, 'fieldName'));
    }

    private function createCustomer(Context $context): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $customer = [
            'id' => $customerId,
            'number' => '1337',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'customerNumber' => '1337',
            'email' => Uuid::randomHex() . '@example.com',
            'password' => 'shopware',
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'customerId' => $customerId,
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];

        static::getContainer()
            ->get('customer.repository')
            ->upsert([$customer], $context);

        return $customerId;
    }

    private function createOrder(string $customerId, Context $context): string
    {
        $orderId = Uuid::randomHex();
        $stateId = static::getContainer()->get(InitialStateIdLoader::class)->get(OrderStates::STATE_MACHINE);
        $billingAddressId = Uuid::randomHex();

        $order = [
            'id' => $orderId,
            'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'orderNumber' => Uuid::randomHex(),
            'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'price' => new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET),
            'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'orderCustomer' => [
                'customerId' => $customerId,
                'email' => 'test@example.com',
                'salutationId' => $this->getValidSalutationId(),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
            ],
            'stateId' => $stateId,
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'currencyId' => Defaults::CURRENCY,
            'currencyFactor' => 1.0,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'billingAddressId' => $billingAddressId,
            'addresses' => [
                [
                    'id' => $billingAddressId,
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                    'countryId' => $this->getValidCountryId(),
                ],
            ],
            'lineItems' => [],
            'deliveries' => [
            ],
            'transactions' => [
                [
                    'paymentMethodId' => $this->getValidPaymentMethodId(),
                    'stateId' => $stateId,
                    'amount' => new CalculatedPrice(200, 200, new CalculatedTaxCollection(), new TaxRuleCollection()),
                ],
            ],
            'context' => '{}',
            'payload' => '{}',
        ];

        $orderRepository = static::getContainer()->get('order.repository');

        $orderRepository->upsert([$order], $context);

        return $orderId;
    }

    private function createDocumentWithFile(string $orderId, Context $context, string $documentType = InvoiceRenderer::TYPE): string
    {
        $documentGenerator = static::getContainer()->get(DocumentGenerator::class);

        $operation = new DocumentGenerateOperation($orderId, FileTypes::PDF, []);
        $document = $documentGenerator->generate($documentType, [$orderId => $operation], $context)->getSuccess()->first();

        static::assertNotNull($document);

        return $document->getId();
    }

    private function createSimpleMailTemplate(Context $context): MailTemplateEntity
    {
        $typeCriteria = new Criteria();
        $typeCriteria->setLimit(1);

        /** @var EntityRepository<MailTemplateTypeCollection> $mailTemplateTypeRepository */
        $mailTemplateTypeRepository = static::getContainer()->get('mail_template_type.repository');
        $mailTemplateType = $mailTemplateTypeRepository->search($typeCriteria, $context)->first();

        static::assertInstanceOf(MailTemplateTypeEntity::class, $mailTemplateType);

        $mailTemplateId = Uuid::randomHex();

        /** @var EntityRepository<MailTemplateCollection> $mailTemplateRepository */
        $mailTemplateRepository = static::getContainer()->get('mail_template.repository');
        $mailTemplateRepository->create([[
            'id' => $mailTemplateId,
            'mailTemplateTypeId' => $mailTemplateType->getId(),
            'subject' => 'Hello {{ customName }}',
            'senderName' => 'Shopware',
            'contentHtml' => '<p>Hello {{ customName }}</p>',
            'contentPlain' => 'Hello {{ customName }}',
        ]], $context);

        $mailTemplate = $mailTemplateRepository->search(new Criteria([$mailTemplateId]), $context)->first();

        static::assertInstanceOf(MailTemplateEntity::class, $mailTemplate);

        return $mailTemplate;
    }
}
