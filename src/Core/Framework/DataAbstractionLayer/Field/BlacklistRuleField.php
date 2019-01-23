<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Inherited;

class BlacklistRuleField extends ListField
{
    public function __construct()
    {
        parent::__construct('blacklist_ids', 'blacklistIds', IdField::class);
        $this->addFlags(new Inherited());
    }
}
