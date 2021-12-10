<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Entity\Xml\Field\Traits;

trait RequiredTrait
{
    protected bool $required;

    public function isRequired(): bool
    {
        return $this->required;
    }
}
