<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Adapter;

use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Routing\IdResolution\ParameterBinding;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('discovery')]
class QueryPlaceholderBinder
{
    public function __construct(
        private readonly DefinitionInstanceRegistry $definitionRegistry,
    ) {
    }

    /**
     * Applies placeholder name mapping and entity resolution to query parameters.
     *
     * NULL bindings = identity mapping (passthrough).
     *
     * @param array<string, ParameterBinding>|null $bindings
     * @param array<string, mixed> $queryParameters
     *
     * @return array<string, mixed>
     */
    public function process(?array $bindings, array $queryParameters, SalesChannelContext $context): array
    {
        if ($bindings === null || $bindings === []) {
            return $queryParameters;
        }

        $result = [];

        foreach ($bindings as $paramName => $binding) {
            if (!\array_key_exists($paramName, $queryParameters)) {
                continue;
            }

            $value = $queryParameters[$paramName];
            $placeholder = $binding->placeholder ?? $paramName;

            if ($binding->resolution === null) {
                $result[$placeholder] = $value;

                continue;
            }

            $resolved = $this->resolveEntityId(
                $binding->resolution->entity,
                $binding->resolution->matchField,
                $value,
                $placeholder,
                $context
            );

            $result[$placeholder] = $resolved;
        }

        return $result;
    }

    private function resolveEntityId(
        string $entityType,
        string $matchField,
        mixed $value,
        string $placeholder,
        SalesChannelContext $context
    ): string {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter($matchField, $value));
        $criteria->setLimit(1);

        if ($matchField !== 'id') {
            $criteria->addFields(['id', $matchField]);
        }

        $repository = $this->definitionRegistry->getRepository($entityType);
        $result = $repository->search($criteria, $context->getContext());
        $entity = $result->first();

        if ($entity === null) {
            $valueStr = \is_scalar($value) ? (string) $value : (\json_encode($value) ?: 'complex value');

            throw ContentSystemException::parameterResolutionFailed(
                $entityType,
                $matchField,
                $valueStr,
                $placeholder
            );
        }

        return $entity->getUniqueIdentifier();
    }
}
