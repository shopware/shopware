<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Attribute;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class ManyToMany extends Field
{
    public const TYPE = 'many-to-many';

    public function __construct(
        public string $entity,
        public OnDelete $onDelete = OnDelete::NO_ACTION,
        public bool|array $api = false,
        public ?string $mapping = null
    ) {
        parent::__construct(type: self::TYPE, api: $api);
    }
}
