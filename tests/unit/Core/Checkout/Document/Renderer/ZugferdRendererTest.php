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
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(ZugferdRenderer::class)]
class ZugferdRendererTest extends TestCase
{
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

        static::assertEquals('zugferd_invoice', $renderer->supports());
    }

    public function testRender(): void
    {
        $order = new OrderEntity();
        $order->setId('0192b305fddb7347be83a311a82f0649');
        $order->setSalesChannelId(Uuid::randomHex());

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([['language_id' => Defaults::LANGUAGE_SYSTEM, 'ids' => '0192b305fddb7347be83a311a82f0649']]);

        $builder = $this->createMock(ZugferdBuilder::class);
        $builder
            ->expects($this->once())
            ->method('buildDocument')
            ->willReturn('<?xml version="1.0" encoding="UTF-8"?>');

        /** @var StaticEntityRepository<OrderCollection> $staticRepository */
        $staticRepository = new StaticEntityRepository([new OrderCollection([$order])], new OrderDefinition());

        $renderer = new ZugferdRenderer(
            $staticRepository,
            $connection,
            $builder,
            $this->createMock(EventDispatcherInterface::class),
            new DocumentConfigLoader($this->createMock(EntityRepository::class), $this->createMock(EntityRepository::class)),
            $this->createMock(NumberRangeValueGeneratorInterface::class)
        );

        $rendered = $renderer->render(
            ['0192b305fddb7347be83a311a82f0649' => new DocumentGenerateOperation('0192b305fddb7347be83a311a82f0649')],
            Context::createDefaultContext(),
            new DocumentRendererConfig()
        )->getOrderSuccess('0192b305fddb7347be83a311a82f0649');

        static::assertNotNull($rendered);
        static::assertEquals(FileTypes::XML, $rendered->getFileExtension());
        static::assertEquals('application/xml', $rendered->getContentType());
        static::assertStringStartsWith('<?xml ', $rendered->getContent());
    }
}
