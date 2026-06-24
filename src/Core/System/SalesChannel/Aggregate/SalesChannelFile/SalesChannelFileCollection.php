<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Aggregate\SalesChannelFile;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<SalesChannelFileEntity>
 */
#[Package('framework')]
class SalesChannelFileCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'sales_channel_file_collection';
    }

    protected function getExpectedClass(): string
    {
        return SalesChannelFileEntity::class;
    }
}
