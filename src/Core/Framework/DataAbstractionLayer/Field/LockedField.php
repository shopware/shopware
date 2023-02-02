<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Computed;

class LockedField extends BoolField
{
    public function __construct()
    {
        parent::__construct('locked', 'locked');

        $this->addFlags(new Computed());
    }
}
