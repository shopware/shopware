<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Attribute;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class CustomFields extends Field
{
    public const TYPE = 'custom-fields';

    public function __construct(public bool $translated = false, public ?string $storageName = null)
    {
        parent::__construct(type: self::TYPE, translated: $this->translated, api: true, storageName: $storageName);
    }
}
