<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Entity\Xml\Field\Traits;

trait TranslatableTrait
{
    protected bool $translatable;

    public function isTranslatable(): bool
    {
        return $this->translatable;
    }
}
