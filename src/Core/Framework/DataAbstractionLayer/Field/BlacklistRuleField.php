<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;
use Shopware\Core\Framework\Feature;

class BlacklistRuleField extends ListField
{
    public function __construct()
    {
        if (Feature::isActive('FEATURE_NEXT_10514')) {
            throw new \RuntimeException('Blacklist rule field will be removed');
        }

        parent::__construct('blacklist_ids', 'blacklistIds', IdField::class);
        $this->addFlags(new Inherited());
    }

    protected function getSerializerClass(): string
    {
        return JsonFieldSerializer::class;
    }
}
