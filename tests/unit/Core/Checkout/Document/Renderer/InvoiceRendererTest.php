<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Renderer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Service\DocumentConfigLoader;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Document\Twig\DocumentTemplateRenderer;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @covers \Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer
 */
#[Package('checkout')]
class InvoiceRendererTest extends TestCase
{
    public function testLanguageIdChainAssignedCorrectly(): void
    {
        $context = Context::createDefaultContext();

        $order = $this->createOrder();

        $orderId = $order->getId();
        $orderCollection = new OrderCollection([$order]);
        $orderSearchResult = new EntitySearchResult(OrderDefinition::ENTITY_NAME, 1, $orderCollection, null, new Criteria(), $context);

        $DELanguageId = Uuid::randomHex();

        $ordersLanguageId = [
            [
                'language_id' => $DELanguageId,
                'ids' => $orderId,
            ],
            [
                'language_id' => Defaults::LANGUAGE_SYSTEM,
                'ids' => $orderId,
            ],
        ];

        $connectionMock = $this->createMock(Connection::class);
        $connectionMock->method('fetchAllAssociative')->willReturn($ordersLanguageId);

        $orderRepositoryMock = $this->createMock(EntityRepository::class);
        $orderRepositoryMock->method('search')->willReturnCallback(function (Criteria $criteria, Context $context) use (&$userCallCount, $DELanguageId, $orderSearchResult) {
            ++$userCallCount;

            switch ($userCallCount) {
                case 1:
                    static::assertCount(2, $context->getLanguageIdChain());
                    static::assertContains(Defaults::LANGUAGE_SYSTEM, $context->getLanguageIdChain());
                    static::assertContains($DELanguageId, $context->getLanguageIdChain());

                    break;
                case 2:
                    static::assertCount(1, $context->getLanguageIdChain());
                    static::assertContains(Defaults::LANGUAGE_SYSTEM, $context->getLanguageIdChain());
            }

            return $orderSearchResult;
        });

        $documentTemplateRenderer = $this->createMock(DocumentTemplateRenderer::class);
        $documentTemplateRenderer->method('render')->willReturn('HTML');

        $invoiceRenderer = new InvoiceRenderer(
            $orderRepositoryMock,
            new DocumentConfigLoader($this->createMock(EntityRepository::class)),
            $this->createMock(EventDispatcherInterface::class),
            $documentTemplateRenderer,
            $this->createMock(NumberRangeValueGeneratorInterface::class),
            '',
            $connectionMock,
        );

        $operations = [
            $orderId => new DocumentGenerateOperation(
                $orderId
            ),
        ];

        $invoiceRenderer->render($operations, $context, new DocumentRendererConfig());
    }

    private function createOrder(): OrderEntity
    {
        $salesChannelId = Uuid::randomHex();
        $salesChannelEntity = new SalesChannelEntity();
        $salesChannelEntity->setId($salesChannelId);

        $language = new LanguageEntity();
        $language->setId('language-test-id');
        $localeEntity = new LocaleEntity();
        $localeEntity->setCode('en-GB');
        $language->setLocale($localeEntity);

        $orderId = Uuid::randomHex();
        $order = new OrderEntity();
        $order->setId($orderId);
        $order->setVersionId(Defaults::LIVE_VERSION);
        $order->setSalesChannelId($salesChannelId);
        $order->setLanguage($language);
        $order->setLanguageId('language-test-id');

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setAccountType(CustomerEntity::ACCOUNT_TYPE_PRIVATE);
        $orderCustomer = new OrderCustomerEntity();
        $orderCustomer->setOrder($order);
        $orderCustomer->setCustomer($customer);
        $order->setOrderCustomer($orderCustomer);

        return $order;
    }
}
