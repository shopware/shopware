<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Field\Traits;

/**
 * @internal
 * @package core
 */
trait RequiredTrait
{
    protected bool $required = false;

    public function isRequired(): bool
    {
        return $this->required;
    }
}
