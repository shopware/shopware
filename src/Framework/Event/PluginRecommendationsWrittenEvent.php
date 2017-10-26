<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class PluginRecommendationsWrittenEvent extends AbstractWrittenEvent
{
    const NAME = 's_plugin_recommendations.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 's_plugin_recommendations';
    }
}
