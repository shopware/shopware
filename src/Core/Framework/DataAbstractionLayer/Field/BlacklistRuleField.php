<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

class BlacklistRuleField extends ListField
{
    public function __construct()
    {
        parent::__construct('blacklisted_rule_ids', 'blacklistedRuleIds', IdField::class);
    }
}
