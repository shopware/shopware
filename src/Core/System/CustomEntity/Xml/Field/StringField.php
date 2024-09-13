<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Field;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomEntity\Xml\Field\Traits\RequiredTrait;
use Shopware\Core\System\CustomEntity\Xml\Field\Traits\TranslatableTrait;

/**
 * @internal
 */
#[Package('core')]
class StringField extends Field
{
    use RequiredTrait;
    use TranslatableTrait;

    protected string $type = 'string';

    protected ?string $default = null;

    public function getDefault(): ?string
    {
        return $this->default;
    }
}
