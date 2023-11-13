<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Field;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomEntity\Xml\Field\Traits\RequiredTrait;

/**
 * @internal
 */
#[Package('core')]
class PriceField extends Field
{
    use RequiredTrait;

    protected string $type = 'price';
}
