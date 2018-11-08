<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Field\WhitelistRuleField;

class WhitelistRuleFieldSerializer extends ListFieldSerializer
{
    public function getFieldClass(): string
    {
        return WhitelistRuleField::class;
    }
}
