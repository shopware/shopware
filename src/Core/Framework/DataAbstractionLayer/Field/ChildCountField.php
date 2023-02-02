<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;

class ChildCountField extends IntField
{
    public function __construct()
    {
        parent::__construct('child_count', 'childCount');
        $this->addFlags(new WriteProtected());
    }
}
