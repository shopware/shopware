<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Rule\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Rule\DataAbstractionLayer\RuleAreaUpdater;
use Shopware\Core\Content\Rule\DataAbstractionLayer\RuleIndexer;
use Shopware\Core\Content\Rule\DataAbstractionLayer\RuleIndexingMessage;
use Shopware\Core\Content\Rule\DataAbstractionLayer\RulePayloadUpdater;
use Shopware\Core\Content\Rule\Event\RuleIndexerEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(RuleIndexer::class)]
class RuleIndexerTest extends TestCase
{
    public function testHandleUpdatesTimestamp(): void
    {
        $connection = $this->createMock(Connection::class);
        $payloadUpdater = $this->createMock(RulePayloadUpdater::class);
        $areaUpdater = $this->createMock(RuleAreaUpdater::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $indexer = new RuleIndexer(
            $connection,
            $this->createMock(IteratorFactory::class),
            $this->createMock(EntityRepository::class),
            $payloadUpdater,
            $areaUpdater,
            $eventDispatcher
        );

        $ids = [Uuid::randomHex(), Uuid::randomHex()];
        $message = new RuleIndexingMessage($ids);

        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                'UPDATE `rule` SET updated_at = :now WHERE id IN (:ids)',
                static::callback(function (array $params) use ($ids): bool {
                    static::assertSame(Uuid::fromHexToBytesList($ids), $params['ids']);
                    static::assertNotFalse(
                        \DateTime::createFromFormat(Defaults::STORAGE_DATE_TIME_FORMAT, $params['now']),
                        'Timestamp must match STORAGE_DATE_TIME_FORMAT'
                    );

                    return true;
                }),
                ['ids' => ArrayParameterType::BINARY]
            );

        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(RuleIndexerEvent::class));

        $indexer->handle($message);
    }
}
