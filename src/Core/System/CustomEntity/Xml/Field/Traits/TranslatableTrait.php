<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Field\Traits;

/**
 * @internal
 */
trait TranslatableTrait
{
    protected bool $translatable;

    public function isTranslatable(): bool
    {
        return $this->translatable;
    }
}
