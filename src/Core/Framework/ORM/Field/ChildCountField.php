<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Field;

use Shopware\Core\Framework\ORM\Write\Flag\ReadOnly;

class ChildCountField extends IntField
{
    public function __construct()
    {
        parent::__construct('child_count', 'childCount');
        $this->setFlags(new ReadOnly());
    }
}
