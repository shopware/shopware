<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding;

use Shopware\Core\Framework\Log\Package;

/**
 * One `inputs` entry of a {@see BindingSpecification}: a residual primitive property key with an optional
 * typed default. Presence is modeled explicitly so "no default" is distinct from "default is null".
 *
 * @internal
 */
#[Package('framework')]
final readonly class BindingInput
{
    public function __construct(
        private bool $hasDefault,
        private mixed $default,
    ) {
    }

    public function hasDefault(): bool
    {
        return $this->hasDefault;
    }

    public function default(): mixed
    {
        return $this->default;
    }
}
