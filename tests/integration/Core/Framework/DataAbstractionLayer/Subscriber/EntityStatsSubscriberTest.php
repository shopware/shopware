<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Histogram;
use Shopware\Core\Framework\Test\Telemetry\Transport\TraceableTransport;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class EntityStatsSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testOnEntitySearchedEmitsAssociationsCountMetric(): void
    {
        Feature::skipTestIfInActive('TELEMETRY_METRICS', $this);

        $transport = $this->getContainer()->get(TraceableTransport::class);
        static::assertInstanceOf(TraceableTransport::class, $transport);
        $transport->reset();
        $userRepo = $this->getContainer()->get('user.repository');
        $userRepo->search(new Criteria(), Context::createDefaultContext())->first();

        static::assertEquals(
            new Histogram(
                name: 'dal.association.count',
                value: 0,
                description: 'Number of associations in request',
            ),
            $transport->getEmittedMetrics()[0]
        );
    }
}
