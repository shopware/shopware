<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding\Specification\Dto;

use Shopware\Core\Framework\ContentSystem\Binding\BindingInput;
use Shopware\Core\Framework\ContentSystem\Binding\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\Binding\LoaderBinding;
use Shopware\Core\Framework\ContentSystem\Binding\Validation\TypeConsistentBindingSpecification;
use Shopware\Core\Framework\ContentSystem\Binding\Validation\WellFormedBindingSpecification;
use Shopware\Core\Framework\Log\Package;

/**
 * Deserialization + load-time validation shape for one binding specification declaration. The id is
 * not carried here — it comes from the YAML body and is supplied to toBindingSpecification().
 *
 * @internal
 */
#[Package('framework')]
#[WellFormedBindingSpecification]
#[TypeConsistentBindingSpecification]
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

            $inputs[(string) $key] = new BindingInput(\array_key_exists('default', $entry), $entry['default'] ?? null);
        }

        return $inputs;
    }

    private function buildLabel(): string
    {
        return \is_string($this->label) ? $this->label : '';
    }
}
