<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\ScheduledTask;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

#[Package('sales-channel')]
class ProductExportGenerateTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'product_export_generate_task';
    }

    public static function getDefaultInterval(): int
    {
        return 60;
    }
}
