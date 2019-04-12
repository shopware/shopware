<?php declare(strict_types=1);

namespace Shopware\Core\Framework;

trait PluginTrait
{
    /**
     * @var bool
     */
    private $active;

    final public function __construct(bool $active = true, ?string $path = null, ?string $name = null)
    {
        $this->active = $active;
        $this->path = $path;
        $this->name = $name;
    }

    final public function isActive(): bool
    {
        return $this->active;
    }

    public function registerBundles(): \Generator
    {
        yield $this;
    }
}
