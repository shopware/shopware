<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Attribute;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class AllowHtml
{
    public function __construct(public bool $sanitized = false)
    {
    }
}
