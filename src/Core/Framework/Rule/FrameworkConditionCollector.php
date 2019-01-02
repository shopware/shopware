<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

use Shopware\Core\Framework\Rule\Collector\CollectConditionEvent;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Container\NotRule;
use Shopware\Core\Framework\Rule\Container\OrRule;
use Shopware\Core\Framework\Rule\Container\XorRule;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FrameworkConditionCollector implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            CollectConditionEvent::NAME => 'collectConditions',
        ];
    }

    public function collectConditions(CollectConditionEvent $event)
    {
        $event->addClasses(
            AndRule::class,
            OrRule::class,
            NotRule::class,
            XorRule::class,
            CurrencyRule::class,
            DateRangeRule::class,
            SalesChannelRule::class
        );
    }
}
