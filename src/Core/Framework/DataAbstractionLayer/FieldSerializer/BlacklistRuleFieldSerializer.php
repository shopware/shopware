<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Field\BlacklistRuleField;

class BlacklistRuleFieldSerializer extends ListFieldSerializer
{
    public function getFieldClass(): string
    {
        return BlacklistRuleField::class;
    }
}
