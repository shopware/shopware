<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Renderer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Renderer\DeliveryNoteRenderer;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopware\Core\Checkout\Document\Service\DocumentConfigLoader;
use Shopware\Core\Checkout\Document\Service\DocumentFileRendererRegistry;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
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
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DeliveryNoteRenderer::class)]
class DeliveryNoteRendererTest extends TestCase
{
    public function testRenderCreatesNewOrderVersion(): void
    {
        $context = Context::createDefaultContext();

        $documentConfigLoaderMock = new DocumentConfigLoader(
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class)
        );

        $order = $this->createOrder();
        $orderId = $order->getId();
        $orderSearchResult = new EntitySearchResult(
            OrderDefinition::ENTITY_NAME,
            1,
            new OrderCollection([$order]),
            null,
            new Criteria(),
            $context
        );

        $orderRepositoryMock = $this->createMock(EntityRepository::class);
        $orderRepositoryMock
            ->expects($this->once())
            ->method('search')
            ->willReturn($orderSearchResult);

        $orderRepositoryMock
            ->expects($this->once())
            ->method('createVersion')
            ->willReturn('new-order-version-id');

        $connectionMock = $this->createMock(Connection::class);
        $connectionMock
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'language_id' => Defaults::LANGUAGE_SYSTEM,
                    'ids' => $orderId,
                ],
            ]);

        $deliveryNoteRenderer = new DeliveryNoteRenderer(
            $orderRepositoryMock,
            $documentConfigLoaderMock,
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(NumberRangeValueGeneratorInterface::class),
            $connectionMock,
            $this->createMock(DocumentFileRendererRegistry::class),
        );

        $operations = [
            $orderId => new DocumentGenerateOperation(
                $orderId
            ),
        ];

        $result = $deliveryNoteRenderer->render($operations, $context, new DocumentRendererConfig());

        static::assertArrayHasKey($orderId, $result->getSuccess());
        static::assertCount(0, $result->getErrors());
    }

    private function createOrder(): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setSalesChannelId(Uuid::randomHex());
        $order->setVersionId(Defaults::LIVE_VERSION);

        $language = new LanguageEntity();
        $language->setId('language-test-id');
        $localeEntity = new LocaleEntity();
        $localeEntity->setCode('en-GB');
        $language->setLocale($localeEntity);

        $order->setLanguage($language);
        $order->setLanguageId('language-test-id');

        return $order;
    }
}
