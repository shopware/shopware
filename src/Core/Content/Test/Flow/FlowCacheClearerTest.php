<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Flow;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\FlowCacheClearer;
use Shopware\Core\Content\Flow\FlowDispatcher;

class FlowCacheClearerTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertEquals([
            'flow.written' => 'clearFlowCache',
            'flow_sequence.written' => 'clearFlowCache',
        ], FlowCacheClearer::getSubscribedEvents());
    }

    public function testClearFlowCache(): void
    {
        $dispatcherMock = $this->createMock(FlowDispatcher::class);
        $dispatcherMock->expects(static::once())
            ->method('clearInternalFlowCache');

        $cacheClearer = new FlowCacheClearer($dispatcherMock);
        $cacheClearer->clearFlowCache();
    }
}
