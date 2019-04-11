<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;

class WhitelistRuleField extends ListField
{
    public function __construct()
    {
        parent::__construct('whitelist_ids', 'whitelistIds', IdField::class);
        $this->addFlags(new Inherited());
    }

    protected function getSerializerClass(): string
    {
        return JsonFieldSerializer::class;
    }
}
