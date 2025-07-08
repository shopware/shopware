<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Renderer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopware\Core\Checkout\Document\Renderer\ZugferdRenderer;
use Shopware\Core\Checkout\Document\Service\DocumentConfigLoader;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Document\Zugferd\ZugferdBuilder;
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
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(ZugferdRenderer::class)]
class ZugferdRendererTest extends TestCase
{
    private const ORDER_ID = '0192b305fddb7347be83a311a82f0649';

    public function testSupports(): void
    {
        $renderer = new ZugferdRenderer(
            $this->createMock(EntityRepository::class),
            $this->createMock(Connection::class),
            $this->createMock(ZugferdBuilder::class),
            $this->createMock(EventDispatcherInterface::class),
            new DocumentConfigLoader($this->createMock(EntityRepository::class), $this->createMock(EntityRepository::class)),
            $this->createMock(NumberRangeValueGeneratorInterface::class)
        );

        static::assertSame('zugferd_invoice', $renderer->supports());
    }

    public function testRender(): void
    {
        $order = new OrderEntity();
        $order->setId(self::ORDER_ID);
        $order->setSalesChannelId(Uuid::randomHex());

        $orderSearchResult = new EntitySearchResult(
            OrderDefinition::ENTITY_NAME,
            1,
            new OrderCollection([$order]),
            null,
            new Criteria(),
            Context::createDefaultContext()
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

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([['language_id' => Defaults::LANGUAGE_SYSTEM, 'ids' => self::ORDER_ID]]);

        $builder = $this->createMock(ZugferdBuilder::class);
        $builder
            ->expects($this->once())
            ->method('buildDocument')
            ->willReturn('<?xml version="1.0" encoding="UTF-8"?>');

        $renderer = new ZugferdRenderer(
            $orderRepositoryMock,
            $connection,
            $builder,
            $this->createMock(EventDispatcherInterface::class),
            new DocumentConfigLoader($this->createMock(EntityRepository::class), $this->createMock(EntityRepository::class)),
            $this->createMock(NumberRangeValueGeneratorInterface::class)
        );

        $rendered = $renderer->render(
            [self::ORDER_ID => new DocumentGenerateOperation(self::ORDER_ID)],
            Context::createDefaultContext(),
            new DocumentRendererConfig()
        )->getOrderSuccess(self::ORDER_ID);

        static::assertNotNull($rendered);
        static::assertSame(FileTypes::XML, $rendered->getFileExtension());
        static::assertSame('application/xml', $rendered->getContentType());
        static::assertStringStartsWith('<?xml ', $rendered->getContent());
    }
}
