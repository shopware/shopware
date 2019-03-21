<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DependencyInjection\fixtures;

final class TestBusinessEvents
{
    /**
     * @Event("Shopware\Core\Framework\Event\BusinessEvent")
     */
    public const GLOBAL_EVENT = 'shopware.global_business_event';

    /**
     * @Event("Shopware\Core\Framework\Test\DependencyInjection\fixtures\TestEvent")
     */
    public const TEST_EVENT = TestEvent::EVENT_NAME;

    private function __construct()
    {
    }
}
