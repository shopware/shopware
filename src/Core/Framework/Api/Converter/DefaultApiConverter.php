<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Converter;

use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Deprecated;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\RequestStack;

class DefaultApiConverter
{
    /**
     * @var DefinitionInstanceRegistry
     */
    private $definitionInstanceRegistry;

    /**
     * @var array
     */
    private $deprecations = [];

    /**
     * @var RequestStack
     */
    private $requestStack;

    public function __construct(DefinitionInstanceRegistry $definitionInstanceRegistry, RequestStack $requestStack)
    {
        $this->definitionInstanceRegistry = $definitionInstanceRegistry;
        $this->requestStack = $requestStack;
    }

    public function convert(int $apiVersion, string $entityName, array $payload): array
    {
        $definition = $this->definitionInstanceRegistry->getByEntityName($entityName);

        $fields = $definition->getFields()->filterByFlag(Deprecated::class);
        if ($fields->count() === 0) {
            return $payload;
        }

        /** @var Field $field */
        foreach ($fields as $field) {
            /** @var Deprecated $deprecated */
            $deprecated = $field->getFlag(Deprecated::class);

            if (!$deprecated->isDeprecatedInVersion($apiVersion)) {
                continue;
            }

            if ($deprecated->getReplaceBy() === null) {
                continue;
            }

            // When the user sends both fields. The prefer the replaceBy field
            if (isset($payload[$field->getPropertyName()], $payload[$deprecated->getReplaceBy()])) {
                unset($payload[$field->getPropertyName()]);
            }
        }

        return $payload;
    }

    public function isDeprecated(int $apiVersion, string $entityName, ?string $fieldName = null): bool
    {
        if ($this->ignoreDeprecations()) {
            return false;
        }

        if ($fieldName === null) {
            return \array_key_exists($entityName, $this->getDeprecations($apiVersion)) && !\is_array($this->getDeprecations($apiVersion)[$entityName]);
        }

        return \in_array($fieldName, $this->getDeprecations($apiVersion)[$entityName] ?? [], true);
    }

    protected function getDeprecations(int $apiVersion): array
    {
        if (isset($this->deprecations[$apiVersion])) {
            return $this->deprecations[$apiVersion];
        }

        foreach ($this->definitionInstanceRegistry->getDefinitions() as $definition) {
            $this->deprecations[$apiVersion][$definition->getEntityName()] = [];

            $fields = $definition->getFields()->filterByFlag(Deprecated::class);

            /** @var Field $field */
            foreach ($fields as $field) {
                /** @var Deprecated $deprecated */
                $deprecated = $field->getFlag(Deprecated::class);
                if ($deprecated->isRemovedInVersion($apiVersion)) {
                    $this->deprecations[$apiVersion][$definition->getEntityName()][] = $field->getPropertyName();
                }
            }
        }

        return $this->deprecations[$apiVersion];
    }

    protected function ignoreDeprecations(): bool
    {
        // We don't have a request
        if ($this->requestStack->getMasterRequest() === null) {
            return false;
        }

        return $this->requestStack->getMasterRequest()->headers->get(PlatformRequest::HEADER_IGNORE_DEPRECATIONS) === 'true';
    }
}
