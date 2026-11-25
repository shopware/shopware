<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Attribute;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class ManyToMany extends Field
{
    public const TYPE = 'many-to-many';

    /**
     * @param bool|array{admin-api: bool, store-api: bool} $api
     * @param string|null $reversedBy Property name of the automatically generated reverse association on the target entity, added as an entity extension. Leave null to keep the relation one-sided.
     * @param bool|array{admin-api: bool, store-api: bool}|null $reverseApi API visibility of the reverse association. Null mirrors $api.
     * @param OnDelete|null $reverseOnDelete Delete behaviour of the reverse association. Null mirrors $onDelete.
     */
    public function __construct(
        public string $entity,
        public OnDelete $onDelete = OnDelete::NO_ACTION,
        public bool|array $api = false,
        public ?string $mapping = null,
        public ?string $reversedBy = null,
        public bool|array|null $reverseApi = null,
        public ?OnDelete $reverseOnDelete = null
    ) {
        parent::__construct(type: self::TYPE, api: $api);
    }
}
