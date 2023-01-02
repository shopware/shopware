<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream\ScheduledTask;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

#[Package('business-ops')]
class UpdateProductStreamMappingTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'product_stream.mapping.update';
    }

    public static function getDefaultInterval(): int
    {
        return 86400; //24 hours
    }
}
