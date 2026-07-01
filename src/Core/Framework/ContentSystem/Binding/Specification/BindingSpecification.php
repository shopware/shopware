<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding\Specification;

use Shopware\Core\Framework\Log\Package;

/**
 * Immutable artifact authored in YAML, representing one element type's pre-validated data wiring.
 *
 * @internal
 *
 * @phpstan-type BindingSpecificationSchema = array{
 *     id: string,
 *     type: string,
 *     label: string,
 *     resolves: array<string, array{loader: string, config: array<string, mixed>}>,
 *     inputs: array<string, array{default?: mixed}>
 * }
 */
#[Package('framework')]
final readonly class BindingSpecification
{
    /**
     * @param array<string, LoaderBinding> $resolves keyed by reference property key
     * @param array<string, BindingInput> $inputs keyed by primitive property key
     */
    public function __construct(
        private string $id,
        private string $type,
        private string $label,
        private array $resolves,
        private array $inputs,
        private string $source = '',
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function label(): string
    {
        return $this->label;
    }

    /**
     * @return array<string, LoaderBinding>
     */
    public function resolves(): array
    {
        return $this->resolves;
    }

    /**
     * @return array<string, BindingInput>
     */
    public function inputs(): array
    {
        return $this->inputs;
    }

    public function source(): string
    {
        return $this->source;
    }

    /**
     * @return BindingSpecificationSchema
     */
    public function toSchema(): array
    {
        $resolves = [];
        foreach ($this->resolves as $key => $binding) {
            $resolves[$key] = [
                'loader' => $binding->source,
                'config' => $binding->config,
            ];
        }

        $inputs = [];
        foreach ($this->inputs as $key => $input) {
            if (!$input->hasDefault) {
                $inputs[$key] = [];

                continue;
            }

            $inputs[$key] = ['default' => $input->default];
        }

        return [
            'id' => $this->id,
            'type' => $this->type,
            'label' => $this->label,
            'resolves' => $resolves,
            'inputs' => $inputs,
        ];
    }
}
