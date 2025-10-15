<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Robots\Struct;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class RobotsDirective
{
    public function __construct(
        public readonly RobotsDirectiveType $type,
        public readonly string $value
    ) {
    }

    /**
     * Returns whether this directive is path-based (requires domain prefix).
     */
    public function isPathBased(): bool
    {
        return $this->type->isPathBased();
    }

    public function withBasePath(string $basePath): self
    {
        if (!$this->isPathBased()) {
            return $this;
        }

        $normalizedBasePath = '/' . trim($basePath, '/');
        $normalizedValue = '/' . ltrim(trim($this->value), '/');
        $path = $normalizedBasePath . $normalizedValue;

        return new self($this->type, '/' . ltrim($path, '/'));
    }

    public function render(): string
    {
        return $this->type->value . ': ' . $this->value;
    }
}
