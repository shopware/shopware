<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding\Specification;

use Shopware\Core\Framework\Log\Package;

/**
 * One `inputs` entry of a {@see BindingSpecification}. Presence is modeled explicitly (`hasDefault`)
 * so "no default" is distinct from "default is null". `$required` is derived from the wiring by the
 * canonicalizer (never authored); it defaults to `false` so a DB row persisted before the flag existed
 * stays loadable.
 *
 * @internal
 */
#[Package('framework')]
final readonly class BindingInput
{
    public function __construct(
        public bool $hasDefault,
        public mixed $default,
        public bool $required = false,
    ) {
    }
}
