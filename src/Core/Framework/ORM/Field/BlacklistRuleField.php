<?php

namespace Shopware\Core\Framework\ORM\Field;

class BlacklistRuleField extends ListField
{
    public function __construct()
    {
        parent::__construct('blacklisted_rule_ids', 'blacklistedRuleIds', IdField::class);
    }
}