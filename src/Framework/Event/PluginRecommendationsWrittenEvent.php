<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Api\Write\WrittenEvent;

class PluginRecommendationsWrittenEvent extends WrittenEvent
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
