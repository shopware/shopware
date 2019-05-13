<?php declare(strict_types=1);

namespace Shopware\Docs\Inspection;

use Shopware\Core\Framework\Struct\Collection;

class ModuleTagCollection extends Collection
{
    public function merge(array $moduleTags): void
    {
        foreach ($moduleTags as $moduleTag) {
            $this->add($moduleTag);
        }
    }

    public function filterName(string $name): ModuleTagCollection
    {
        return $this->filter(function (ModuleTag $moduleTag) use ($name) {
            return $moduleTag->name() === $name;
        });
    }

    protected function getExpectedClass(): ?string
    {
        return ModuleTag::class;
    }
}
