<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding\Specification\Dto;

use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingInput;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\LoaderBinding;
use Shopware\Core\Framework\ContentSystem\Binding\Validation\WellFormedBindingSpecification;
use Shopware\Core\Framework\Log\Package;

/**
 * The id is not carried here — it is the `bindings:` map key of the entry and is supplied to
 * {@see self::toBindingSpecification()} by the loader.
 *
 * Carries only the structural WellFormedBindingSpecification constraint. The semantic
 * TypeConsistentBindingSpecification constraint lives on {@see BindingSpecificationDtoCollection} so a per-load
 * type overlay can reach its validator.
 *
 * @internal
 */
#[Package('framework')]
#[WellFormedBindingSpecification]
final readonly class BindingSpecificationDto
{
    /**
     * Every facet is carried raw (typed mixed) so the validator can reject a wrong-typed declaration at
     * runtime rather than have it silently coerced before validation sees it. The facets are narrowed to
     * their clean shapes by buildResolves()/buildInputs()/buildLabel(), which run only after validation
     * has passed.
     */
    public function __construct(
        public mixed $type,
        public mixed $label,
        public mixed $resolves,
        public mixed $inputs,
    ) {
    }

    public function toBindingSpecification(string $id, string $source): BindingSpecification
    {
        return new BindingSpecification(
            $id,
            \is_string($this->type) ? $this->type : '',
            $this->buildLabel(),
            $this->buildResolves(),
            $this->buildInputs(),
            $source,
        );
    }

    /**
     * @return array<string, LoaderBinding>
     */
    private function buildResolves(): array
    {
        if (!\is_array($this->resolves)) {
            return [];
        }

        $resolves = [];
        foreach ($this->resolves as $key => $entry) {
            if (!\is_array($entry)) {
                continue;
            }

            $loader = $entry['loader'] ?? null;
            if (!\is_string($loader)) {
                continue;
            }

            $config = $entry['config'] ?? null;
            $resolves[(string) $key] = new LoaderBinding($loader, \is_array($config) ? $config : []);
        }

        return $resolves;
    }

    /**
     * @return array<string, BindingInput>
     */
    private function buildInputs(): array
    {
        if (!\is_array($this->inputs)) {
            return [];
        }

        $inputs = [];
        foreach ($this->inputs as $key => $entry) {
            if (!\is_array($entry)) {
                continue;
            }

            $inputs[(string) $key] = new BindingInput(
                \array_key_exists('default', $entry),
                $entry['default'] ?? null,
                $entry['required'] === true,
            );
        }

        return $inputs;
    }

    private function buildLabel(): string
    {
        return \is_string($this->label) ? $this->label : '';
    }
}
