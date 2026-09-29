<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework\Indexing\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Framework\Indexing\Event\ElasticsearchIndexerLanguageCriteriaEvent;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ElasticsearchIndexerLanguageCriteriaEvent::class)]
class ElasticsearchIndexerLanguageCriteriaEventTest extends TestCase
{
    public function testEvent(): void
    {
        $criteria = new Criteria();
        $context = Context::createDefaultContext();

        $event = new ElasticsearchIndexerLanguageCriteriaEvent($criteria, $context);
        static::assertSame($criteria, $event->getCriteria());
        static::assertSame($context, $event->getContext());
    }
}
