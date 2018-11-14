<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;

class UpdatedAtField extends DateField
{
    public function __construct()
    {
        parent::__construct('updated_at', 'updatedAt');
        $this->setFlags(new Required());
    }
}
