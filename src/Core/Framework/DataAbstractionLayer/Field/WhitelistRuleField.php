<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;
use Shopware\Core\Framework\Feature;

class WhitelistRuleField extends ListField
{
    public function __construct()
    {
        if (Feature::isActive('FEATURE_NEXT_10514')) {
            throw new \RuntimeException('Whitelist rule field will be removed');
        }

        parent::__construct('whitelist_ids', 'whitelistIds', IdField::class);
        $this->addFlags(new Inherited());
    }

    protected function getSerializerClass(): string
    {
        return JsonFieldSerializer::class;
    }
}
