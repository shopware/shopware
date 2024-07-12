<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Attribute;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class AutoIncrement extends Field
{
    public const TYPE = 'auto-increment';

    public bool $nullable;

    public function __construct()
    {
        parent::__construct(type: self::TYPE, api: true);
    }
}
