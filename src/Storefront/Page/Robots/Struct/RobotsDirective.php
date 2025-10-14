<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Robots\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('framework')]
class RobotsDirective extends Struct
{
    public function __construct(
        public readonly string $type,
        public readonly string $value
    ) {
    }

    public static function isPathBased(string $type): bool
    {
        return \in_array(mb_strtolower($type), ['allow', 'disallow'], true);
    }

    public function withBasePath(string $basePath): self
    {
        if (!self::isPathBased($this->type)) {
            return $this;
        }

        $normalizedBasePath = '/' . trim($basePath, '/');
        $normalizedValue = '/' . ltrim(trim($this->value), '/');
        $path = $normalizedBasePath . $normalizedValue;

        return new self($this->type, '/' . ltrim($path, '/'));
    }

    public function render(): string
    {
        return $this->type . ': ' . $this->value;
    }
}
