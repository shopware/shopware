<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DependencyInjection\fixtures;

/**
 * @internal
 */
final class TestBusinessEvents
{
    public const TEST_EVENT = TestEvent::EVENT_NAME;

    private function __construct()
    {
    }
}
