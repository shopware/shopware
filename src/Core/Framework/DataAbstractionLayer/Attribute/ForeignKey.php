<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Attribute;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class ForeignKey extends Field
{
    public const TYPE = 'fk';

    public bool $nullable;

    public function __construct(public string $entity, public bool|array $api = false)
    {
        parent::__construct(type: self::TYPE, api: $api);
    }
}
