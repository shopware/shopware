<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Rule\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Statement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Content\Rule\DataAbstractionLayer\RuleIndexerSubscriber;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(RuleIndexerSubscriber::class)]
class RuleIndexerSubscriberTest extends TestCase
{
    public function testRefreshPlugin(): void
    {
        $connection = $this->createMock(Connection::class);
        $cartRuleLoader = $this->createMock(CartRuleLoader::class);
        $clock = new MockClock('2026-01-13 12:00:00');

        $statement = $this->createMock(Statement::class);

        $statement->expects($this->once())
            ->method('bindValue')
            ->with('updatedAt', $clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT));
        $statement->expects($this->once())->method('executeStatement')->willReturn(1);

        $connection->expects($this->once())
            ->method('prepare')
            ->with('UPDATE `rule` SET `payload` = null, `invalid` = 0, `updated_at` = :updatedAt')
            ->willReturn($statement);

        $subscriber = new RuleIndexerSubscriber($connection, $cartRuleLoader, $clock);

        $subscriber->refreshPlugin();
    }
}
