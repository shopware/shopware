<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Type\CmsTypes;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\CmsPageEntity;

class CustomPage extends CmsPageEntity
{
    public function getFirstElementOfType(string $type): ?CmsSlotEntity
    {
        return null;
    }
}
