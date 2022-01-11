<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\Struct\ArrayEntity;

class PartialEntity extends ArrayEntity
{
    public function getApiAlias(): string
    {
        return 'partial.' . parent::getApiAlias();
    }
}
