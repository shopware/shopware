<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Symfony\ServiceDefinition;
use PHPStan\Symfony\ServiceMap;
use PHPStan\Type\Type;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * @implements Rule<InClassNode>
 *
 * @internal
 */
#[Package('framework')]
class TaggedServiceContractRule implements Rule
{
    /**
     * @var array<string, list<ServiceDefinition>>|null
     */
    private ?array $servicesByClass = null;

    /**
     * @var array<string, list<array{tag: string, argument: int|string, kind: 'iterator'|'locator'}>>|null
     */
    private ?array $taggedArgumentsByServiceId = null;

    /**
     * @param array<string, class-string|list<class-string>> $tagContracts
     * @param array<string, class-string|list<class-string>> $additionalTagContracts
     */
    public function __construct(
        private readonly ServiceMap $serviceMap,
        private readonly ReflectionProvider $reflectionProvider,
        private readonly array $tagContracts,
        private readonly array $additionalTagContracts = [],
        private readonly ?string $containerXmlPath = null
    ) {
    }

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /**
     * @param InClassNode $node
     *
     * @return list<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $class = $node->getClassReflection();
        $className = $class->getName();
        $errors = [];

        foreach ($this->getServicesByClass()[$className] ?? [] as $service) {
            foreach ($this->checkTaggedService($service, $class) as $error) {
                $errors[] = $error;
            }

            foreach ($this->getTaggedArgumentsByServiceId()[$service->getId()] ?? [] as $argument) {
                foreach ($this->checkTaggedArgument($class, $service->getId(), $argument) as $error) {
                    $errors[] = $error;
                }
            }
        }

        foreach ($this->getAttributeTaggedArguments($node) as $argument) {
            foreach ($this->checkTaggedArgument($class, $className, $argument) as $error) {
                $errors[] = $error;
            }
        }

        return $errors;
    }

    /**
     * @return array<string, class-string|list<class-string>>
     */
    private function getTagContracts(): array
    {
        return $this->tagContracts + $this->additionalTagContracts;
    }

    /**
     * @return list<RuleError>
     */
    private function checkTaggedService(ServiceDefinition $service, ClassReflection $class): array
    {
        $errors = [];

        foreach ($service->getTags() as $tag) {
            /** @phpstan-ignore phpstanApi.method (ServiceTag is returned by the public ServiceDefinition API, but this accessor is not marked API) */
            $tagName = $tag->getName();
            $contract = $this->getTagContracts()[$tagName] ?? null;

            if ($contract === null || $this->isClassCompatibleWithContract($class, $contract)) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(\sprintf(
                'Service "%s" is tagged with "%s" but its class "%s" does not implement or extend the configured tag contract "%s".',
                $service->getId(),
                $tagName,
                $class->getName(),
                $this->formatContracts($contract)
            ))
                ->identifier('shopware.taggedServiceContract')
                ->build();
        }

        return $errors;
    }

    /**
     * @param array{tag: string, argument: int|string, kind: 'iterator'|'locator'} $argument
     *
     * @return list<RuleError>
     */
    private function checkTaggedArgument(ClassReflection $class, string $serviceId, array $argument): array
    {
        $parameter = $this->getConstructorParameter($class, $argument['argument']);

        if ($parameter === null) {
            return [];
        }

        $collectionTypes = $this->getCollectionObjectClassNames($parameter->getType(), $argument['kind']);
        $tagContracts = $this->getTagContracts();
        $contract = $tagContracts[$argument['tag']] ?? null;

        if ($contract !== null) {
            if (array_intersect($this->getContractClasses($contract), $collectionTypes) !== []) {
                return [];
            }

            return [
                RuleErrorBuilder::message(\sprintf(
                    'Service "%s" injects services tagged with "%s" into parameter $%s, but the parameter is not typed as the configured tag contract "%s".',
                    $serviceId,
                    $argument['tag'],
                    $parameter->getName(),
                    $this->formatContracts($contract)
                ))
                    ->identifier('shopware.taggedServiceContract')
                    ->build(),
            ];
        }

        $errors = [];
        foreach ($collectionTypes as $collectionType) {
            if (!$this->isPublicTaggedContractCandidate($collectionType)) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(\sprintf(
                'Tagged service tag "%s" is consumed as "%s", but the tag has no declared contract in TaggedServiceContractRule. Add the tag contract to the rule configuration or mark "%s" as @internal.',
                $argument['tag'],
                $collectionType,
                $collectionType
            ))
                ->identifier('shopware.taggedServiceContract')
                ->build();
        }

        return $errors;
    }

    /**
     * @param class-string|list<class-string> $contract
     */
    private function isClassCompatibleWithContract(ClassReflection $class, string|array $contract): bool
    {
        foreach ($this->getContractClasses($contract) as $contractClass) {
            if ($this->isClassCompatibleWithContractClass($class, $contractClass)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param class-string $contract
     */
    private function isClassCompatibleWithContractClass(ClassReflection $class, string $contract): bool
    {
        if ($class->getName() === $contract) {
            return true;
        }

        if (!$this->reflectionProvider->hasClass($contract)) {
            return false;
        }

        $contractReflection = $this->reflectionProvider->getClass($contract);

        if ($contractReflection->isInterface()) {
            return $class->implementsInterface($contract);
        }

        return $class->isSubclassOfClass($contractReflection);
    }

    /**
     * @param class-string|list<class-string> $contract
     *
     * @return list<class-string>
     */
    private function getContractClasses(string|array $contract): array
    {
        return \is_string($contract) ? [$contract] : $contract;
    }

    /**
     * @param class-string|list<class-string> $contract
     */
    private function formatContracts(string|array $contract): string
    {
        return implode('|', $this->getContractClasses($contract));
    }

    private function isPublicTaggedContractCandidate(string $className): bool
    {
        if (!$this->reflectionProvider->hasClass($className)) {
            return false;
        }

        $class = $this->reflectionProvider->getClass($className);

        return ($class->isInterface() || $class->isAbstract()) && !$class->isInternal();
    }

    private function getConstructorParameter(ClassReflection $class, int|string $argument): ?ParameterReflection
    {
        if (!$class->hasConstructor()) {
            return null;
        }

        $parameters = $class->getConstructor()->getVariants()[0]->getParameters();

        if (\is_int($argument)) {
            return $parameters[$argument] ?? null;
        }

        $argument = ltrim($argument, '$');

        foreach ($parameters as $parameter) {
            if ($parameter->getName() === $argument) {
                return $parameter;
            }
        }

        return null;
    }

    /**
     * @param 'iterator'|'locator' $kind
     *
     * @return list<string>
     */
    private function getCollectionObjectClassNames(Type $type, string $kind): array
    {
        if ($kind === 'locator') {
            return $this->getObjectClassNames($type->getTemplateType(ServiceProviderInterface::class, 'T'));
        }

        if ($type->isIterable()->yes()) {
            return $this->getObjectClassNames($type->getIterableValueType());
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function getObjectClassNames(Type $type): array
    {
        return array_values(array_unique($type->getObjectClassNames()));
    }

    /**
     * @return array<string, list<ServiceDefinition>>
     */
    private function getServicesByClass(): array
    {
        if ($this->servicesByClass !== null) {
            return $this->servicesByClass;
        }

        $this->servicesByClass = [];

        foreach ($this->serviceMap->getServices() as $service) {
            $class = $service->getClass();

            if ($class === null) {
                continue;
            }

            $this->servicesByClass[$class][] = $service;
        }

        return $this->servicesByClass;
    }

    /**
     * @return array<string, list<array{tag: string, argument: int|string, kind: 'iterator'|'locator'}>>
     */
    private function getTaggedArgumentsByServiceId(): array
    {
        if ($this->taggedArgumentsByServiceId !== null) {
            return $this->taggedArgumentsByServiceId;
        }

        $this->taggedArgumentsByServiceId = [];

        $container = $this->loadContainerXml();

        if ($container === null || \count($container->services) === 0) {
            return $this->taggedArgumentsByServiceId;
        }

        $locatorTagsByServiceId = $this->getLocatorTagsByServiceId($container);

        foreach ($container->services->service as $service) {
            $serviceId = $this->getXmlAttribute($service, 'id');

            if ($serviceId === null) {
                continue;
            }

            $argumentIndex = 0;

            foreach ($service->argument as $argument) {
                $type = $this->getXmlAttribute($argument, 'type');

                if ($type === 'tagged_iterator') {
                    $tag = $this->getXmlAttribute($argument, 'tag');

                    if ($tag !== null) {
                        $this->taggedArgumentsByServiceId[$serviceId][] = [
                            'tag' => $tag,
                            'argument' => $argumentIndex,
                            'kind' => 'iterator',
                        ];
                    }

                    ++$argumentIndex;

                    continue;
                }

                if ($type !== 'service') {
                    ++$argumentIndex;

                    continue;
                }

                $locatorServiceId = $this->getXmlAttribute($argument, 'id');

                if ($locatorServiceId === null) {
                    continue;
                }

                foreach ($locatorTagsByServiceId[$locatorServiceId] ?? [] as $tag) {
                    $this->taggedArgumentsByServiceId[$serviceId][] = [
                        'tag' => $tag,
                        'argument' => $argumentIndex,
                        'kind' => 'locator',
                    ];
                }

                ++$argumentIndex;
            }
        }

        return $this->taggedArgumentsByServiceId;
    }

    /**
     * @return list<array{tag: string, argument: int|string, kind: 'iterator'|'locator'}>
     */
    private function getAttributeTaggedArguments(InClassNode $node): array
    {
        $constructor = $node->getOriginalNode()->getMethod('__construct');

        if ($constructor === null) {
            return [];
        }

        $arguments = [];

        foreach ($constructor->getParams() as $index => $parameter) {
            foreach ($parameter->attrGroups as $attributeGroup) {
                foreach ($attributeGroup->attrs as $attribute) {
                    $kind = $this->getTaggedAttributeKind($attribute->name->toString());

                    if ($kind === null) {
                        continue;
                    }

                    $tag = $this->getStringArgument($attribute->args[0] ?? null);

                    if ($tag === null) {
                        continue;
                    }

                    $arguments[] = [
                        'tag' => $tag,
                        'argument' => $index,
                        'kind' => $kind,
                    ];
                }
            }
        }

        return $arguments;
    }

    /**
     * @return 'iterator'|'locator'|null
     */
    private function getTaggedAttributeKind(string $attributeName): ?string
    {
        return match (true) {
            str_ends_with($attributeName, 'AutowireIterator'), str_ends_with($attributeName, 'TaggedIterator') => 'iterator',
            str_ends_with($attributeName, 'AutowireLocator'), str_ends_with($attributeName, 'TaggedLocator') => 'locator',
            default => null,
        };
    }

    private function getStringArgument(?Arg $argument): ?string
    {
        $value = $argument?->value;

        if (!$value instanceof Node\Scalar\String_) {
            return null;
        }

        return $value->value;
    }

    private function loadContainerXml(): ?\SimpleXMLElement
    {
        if ($this->containerXmlPath === null || !is_file($this->containerXmlPath)) {
            return null;
        }

        $xml = @simplexml_load_file($this->containerXmlPath);

        return $xml instanceof \SimpleXMLElement ? $xml : null;
    }

    /**
     * @return array<string, list<string>>
     */
    private function getLocatorTagsByServiceId(\SimpleXMLElement $container): array
    {
        $tagsByServiceId = [];

        foreach ($container->services->service as $service) {
            if ($this->getXmlAttribute($service, 'class') !== ServiceLocator::class) {
                continue;
            }

            $serviceId = $this->getXmlAttribute($service, 'id');

            if ($serviceId === null) {
                continue;
            }

            $tags = [];

            foreach ($service->argument->argument as $locatorEntry) {
                if ($this->getXmlAttribute($locatorEntry, 'type') !== 'service_closure') {
                    continue;
                }

                $referencedServiceId = $this->getXmlAttribute($locatorEntry, 'id');

                if ($referencedServiceId === null) {
                    continue;
                }

                $referencedService = $this->serviceMap->getService($referencedServiceId);

                if ($referencedService === null) {
                    continue;
                }

                foreach ($referencedService->getTags() as $tag) {
                    /** @phpstan-ignore phpstanApi.method (ServiceTag is returned by the public ServiceDefinition API, but this accessor is not marked API) */
                    $tagName = $tag->getName();

                    $tags[] = $tagName;
                }
            }

            $tags = array_values(array_unique($tags));

            if ($tags !== []) {
                $tagsByServiceId[$serviceId] = $tags;
            }
        }

        return $tagsByServiceId;
    }

    private function getXmlAttribute(\SimpleXMLElement $element, string $name): ?string
    {
        $attributes = $element->attributes();

        return isset($attributes[$name]) ? (string) $attributes[$name] : null;
    }
}
