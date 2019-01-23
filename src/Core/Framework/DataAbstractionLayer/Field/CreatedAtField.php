<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;

class CreatedAtField extends DateField
{
    public function __construct()
    {
        parent::__construct('created_at', 'createdAt');
        $this->addFlags(new Required());
    }
}
