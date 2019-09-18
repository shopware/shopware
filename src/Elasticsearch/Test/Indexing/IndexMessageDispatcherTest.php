<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Test\Indexing;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Elasticsearch\Framework\Indexing\IndexMessageDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class IndexMessageDispatcherTest extends TestCase
{
    public function getIdArray(): array
    {
        $hexIds = [
            '60040886a0044039b567f64ed25c4ad8',
            '86280931b9e54209a960a5e406d15181',
        ];
        $ids = [];
        foreach ($hexIds as $hexId) {
            $ids[Uuid::fromHexToBytes($hexId)] = $hexId;
        }

        return $ids;
    }

    public function testIndexDefinitionBuildsSerializableMessages(): void
    {
        $iterableQueryMock = $this->getMockBuilder(IterableQuery::class)->getMock();
        $iterableQueryMock
            ->expects(static::exactly(2))
            ->method('fetch')
            ->willReturnOnConsecutiveCalls($this->getIdArray(), []);

        $iteratorFactory = $this->getMockBuilder(IteratorFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $iteratorFactory->expects(static::once())
            ->method('createIterator')
            ->willReturn($iterableQueryMock);

        $messageBusMock = $this->getMockBuilder(MessageBusInterface::class)->getMock();
        $messageBusMock->expects(static::once())
            ->method('dispatch')
            ->with(static::callback(
                function ($msg) {
                    $serializer = new Serializer([new ObjectNormalizer()], [new JsonEncoder()]);
                    $serializedMessage = $serializer->serialize($msg, 'json');

                    return mb_strlen($serializedMessage) > mb_strlen('{}');
                }
            ))
            ->willReturnCallback(function ($msg) { return new Envelope($msg); });

        $eventDispatcherMock = $this->getMockBuilder(EventDispatcherInterface::class)->getMock();

        $definitionMock = $this->getMockBuilder(EntityDefinition::class)->disableOriginalConstructor()->getMock();

        $dispatcher = new IndexMessageDispatcher($iteratorFactory, $eventDispatcherMock, $messageBusMock);

        $dispatcher->dispatchForAllEntities('index', $definitionMock, Context::createDefaultContext());
    }

    public function testDispatchForIds(): void
    {
        $iteratorFactory = $this->getMockBuilder(IteratorFactory::class)
            ->disableOriginalConstructor()
            ->getMock();

        $messageBusMock = $this->getMockBuilder(MessageBusInterface::class)->getMock();
        $messageBusMock->expects(static::once())
            ->method('dispatch')
            ->with(static::callback(
                function ($msg) {
                    $serializer = new Serializer([new ObjectNormalizer()], [new JsonEncoder()]);
                    $serializedMessage = $serializer->serialize($msg, 'json');

                    return mb_strlen($serializedMessage) > mb_strlen('{}');
                }
            ))
            ->willReturnCallback(function ($msg) { return new Envelope($msg); });

        $eventDispatcherMock = $this->getMockBuilder(EventDispatcherInterface::class)->getMock();

        $definitionMock = $this->getMockBuilder(EntityDefinition::class)->disableOriginalConstructor()->getMock();

        $dispatcher = new IndexMessageDispatcher($iteratorFactory, $eventDispatcherMock, $messageBusMock);

        $dispatcher->dispatchForIds($this->getIdArray(), 'index', $definitionMock, Context::createDefaultContext());
    }
}
