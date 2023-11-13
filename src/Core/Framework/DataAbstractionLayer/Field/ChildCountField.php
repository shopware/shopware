<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class ChildCountField extends IntField
{
    public function __construct()
    {
        parent::__construct('child_count', 'childCount');
        $this->addFlags(new WriteProtected());
    }
}
