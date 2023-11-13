<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Field;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomEntity\Xml\Field\Traits\RequiredTrait;
use Shopware\Core\System\CustomEntity\Xml\Field\Traits\TranslatableTrait;

/**
 * @internal
 */
#[Package('core')]
class TextField extends Field
{
    use RequiredTrait;
    use TranslatableTrait;

    protected bool $allowHtml = false;

    protected string $type = 'text';

    public function allowHtml(): bool
    {
        return $this->allowHtml;
    }
}
