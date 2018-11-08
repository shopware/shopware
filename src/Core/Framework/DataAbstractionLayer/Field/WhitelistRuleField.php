<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

class WhitelistRuleField extends ListField
{
    public function __construct()
    {
        parent::__construct('whitelisted_rule_ids', 'whitelistedRuleIds', IdField::class);
    }
}
