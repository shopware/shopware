<?php declare(strict_types=1);

namespace Shopware\Docs\Inspection;

use Shopware\Core\Framework\Struct\Collection;
use Symfony\Component\Finder\SplFileInfo;

class ModuleTagCollection extends Collection
{
    /**
     * @var SplFileInfo
     */
    private $module;

    public function __construct(SplFileInfo $module, iterable $elements = [])
    {
        parent::__construct($elements);
        $this->module = $module;
    }

    public function getModule(): SplFileInfo
    {
        return $this->module;
    }

    public function getModulePathName(): string
    {
        return $this->module->getRelativePathname();
    }

    public function getModuleName(): string
    {
        return explode('/', $this->getModulePathName())[1];
    }

    public function getBundleName(): string
    {
        return explode('/', $this->getModulePathName())[0];
    }

    public function merge(array $moduleTags): void
    {
        foreach ($moduleTags as $moduleTag) {
            $this->add($moduleTag);
        }
    }

    public function filterName(string $name): ModuleTagCollection
    {
        return $this->filter(static function (ModuleTag $moduleTag) use ($name) {
            return $moduleTag->name() === $name;
        });
    }

    protected function getExpectedClass(): ?string
    {
        return ModuleTag::class;
    }

    protected function createNew(iterable $elements = []): ModuleTagCollection
    {
        return new self($this->module, $elements);
    }
}
