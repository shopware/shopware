<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

class WhitelistRuleField extends ListField
{
    public function __construct()
    {
        parent::__construct('whitelist_ids', 'whitelistIds', IdField::class);
    }
}
