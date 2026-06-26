<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use League\OAuth2\Server\Entities\ScopeEntityInterface;
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
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\CartValidatorInterface;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupPackagerInterface;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupSorterInterface;
use Shopware\Core\Checkout\Cart\LineItemFactoryHandler\LineItemFactoryInterface;
use Shopware\Core\Checkout\Cart\TaxProvider\AbstractTaxProvider;
use Shopware\Core\Checkout\Customer\Password\LegacyEncoder\LegacyEncoderInterface;
use Shopware\Core\Checkout\Document\Renderer\AbstractDocumentRenderer;
use Shopware\Core\Checkout\Document\Service\AbstractDocumentTypeRenderer;
use Shopware\Core\Checkout\Gateway\Command\Handler\AbstractCheckoutGatewayCommandHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\FilterPickerInterface;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\FilterSorterInterface;
use Shopware\Core\Content\Cms\DataResolver\Element\CmsElementResolverInterface;
use Shopware\Core\Content\Flow\Dispatching\Action\FlowAction;
use Shopware\Core\Content\Flow\Dispatching\Storer\FlowStorer;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\AbstractEntitySerializer;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\AbstractFieldSerializer;
use Shopware\Core\Content\ImportExport\Processing\Reader\AbstractReaderFactory;
use Shopware\Core\Content\ImportExport\Processing\Writer\AbstractWriterFactory;
use Shopware\Core\Content\Media\Core\Application\AbstractMediaPathStrategy;
use Shopware\Core\Content\Media\File\AbstractFileContentValidator;
use Shopware\Core\Content\Media\Metadata\MetadataLoader\MetadataLoaderInterface;
use Shopware\Core\Content\Media\TypeDetector\TypeDetectorInterface;
use Shopware\Core\Content\Product\Cms\ProductSlider\AbstractProductSliderProcessor;
use Shopware\Core\Content\Product\DataAbstractionLayer\StockUpdate\AbstractStockUpdateFilter;
use Shopware\Core\Content\Product\SalesChannel\Listing\Filter\AbstractListingFilterHandler;
use Shopware\Core\Content\Product\SalesChannel\Listing\Processor\AbstractListingProcessor;
use Shopware\Core\Content\ProductExport\Provider\AbstractAgenticCommerceProductExportProvider;
use Shopware\Core\Content\ProductExport\Validator\ValidatorInterface;
use Shopware\Core\Content\Seo\SeoUrlRoute\EntitySeoUrlRouteInterface;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\MailFlowDataProviderInterface;
use Shopware\Core\Content\Sitemap\ConfigHandler\ConfigHandlerInterface;
use Shopware\Core\Content\Sitemap\Provider\AbstractUrlProvider;
use Shopware\Core\Framework\Adapter\Filesystem\Adapter\AdapterFactoryInterface;
use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\TemplateNamespaceHierarchyBuilderInterface;
use Shopware\Core\Framework\Api\Sync\AbstractFkResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\FieldEnumProviderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Increment\AbstractIncrementer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Shopware\Core\Framework\Routing\AbstractRouteScope;
use Shopware\Core\Framework\Routing\RouteScopeWhitelistInterface;
use Shopware\Core\Framework\SystemCheck\BaseCheck;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\PeriodicMetricCollectorInterface;
use Shopware\Core\Framework\Telemetry\Metrics\MetricTransportInterface;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\AbstractValueGenerator;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\AbstractIncrementStorage;
use Shopware\Core\System\Snippet\Filter\SnippetFilterInterface;
use Shopware\Core\System\Tax\TaxRuleType\TaxRuleTypeFilterInterface;
use Shopware\Elasticsearch\Admin\Indexer\AbstractAdminIndexer;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopware\Storefront\Framework\Captcha\AbstractCaptcha;
use Shopware\Storefront\Framework\Media\StorefrontMediaValidatorInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Contracts\Service\ServiceProviderInterface;
use Twig\Extension\ExtensionInterface;

/**
 * @implements Rule<InClassNode>
 *
 * @internal
 */
#[Package('framework')]
class TaggedServiceContractRule implements Rule
{
    /**
     * Add tags here once their tagged services are a supported extension contract.
     * Changing a class in this array is a breaking change and must not be done in a minor release.
     *
     * @var array<string, class-string>
     */
    private const TAG_CONTRACTS = [
        'document.renderer' => AbstractDocumentRenderer::class,
        'document_type.renderer' => AbstractDocumentTypeRenderer::class,
        'flow.action' => FlowAction::class,
        'flow.storer' => FlowStorer::class,
        'lineitem.group.packager' => LineItemGroupPackagerInterface::class,
        'lineitem.group.sorter' => LineItemGroupSorterInterface::class,
        'messenger.receiver' => ReceiverInterface::class,
        'promotion.filter.picker' => FilterPickerInterface::class,
        'promotion.filter.sorter' => FilterSorterInterface::class,
        'shopware.api.enum_provider' => FieldEnumProviderInterface::class,
        'shopware.app_script.twig.extension' => ExtensionInterface::class,
        'shopware.cart.collector' => CartDataCollectorInterface::class,
        'shopware.cart.line_item.factory' => LineItemFactoryInterface::class,
        'shopware.cart.processor' => CartProcessorInterface::class,
        'shopware.cart.validator' => CartValidatorInterface::class,
        'shopware.checkout.gateway.command' => AbstractCheckoutGatewayCommandHandler::class,
        'shopware.cms.data_resolver' => CmsElementResolverInterface::class,
        'shopware.cms.product_slider.processor' => AbstractProductSliderProcessor::class,
        'shopware.dal.exception_handler' => ExceptionHandlerInterface::class,
        'shopware.demodata_generator' => DemodataGeneratorInterface::class,
        /** @phpstan-ignore phpat.restrictNamespacesInCore (only class constant is used) */
        'shopware.elastic.admin-searcher-index' => AbstractAdminIndexer::class,
        'shopware.entity.definition' => EntityDefinition::class,
        'shopware.entity.hookable' => EntityDefinition::class,
        'shopware.entity.seo_url.route' => EntitySeoUrlRouteInterface::class,
        'shopware.entity_indexer' => EntityIndexer::class,
        /** @phpstan-ignore phpat.restrictNamespacesInCore (only class constant is used) */
        'shopware.es.definition' => AbstractElasticsearchDefinition::class,
        'shopware.filesystem.factory' => AdapterFactoryInterface::class,
        'shopware.import_export.entity_serializer' => AbstractEntitySerializer::class,
        'shopware.import_export.field_serializer' => AbstractFieldSerializer::class,
        'shopware.import_export.reader_factory' => AbstractReaderFactory::class,
        'shopware.import_export.writer_factory' => AbstractWriterFactory::class,
        'shopware.increment.gateway' => AbstractIncrementer::class,
        'shopware.legacy_encoder' => LegacyEncoderInterface::class,
        'shopware.listing.filter.handler' => AbstractListingFilterHandler::class,
        'shopware.listing.processor' => AbstractListingProcessor::class,
        'shopware.mail.data_provider' => MailFlowDataProviderInterface::class,
        'shopware.media.file_content.validator' => AbstractFileContentValidator::class,
        'shopware.media_type.detector' => TypeDetectorInterface::class,
        'shopware.metadata.loader' => MetadataLoaderInterface::class,
        'shopware.metric_transport_factory' => MetricTransportInterface::class,
        'shopware.oauth.scope' => ScopeEntityInterface::class,
        'shopware.path.strategy' => AbstractMediaPathStrategy::class,
        'shopware.payment.method' => AbstractPaymentHandler::class,
        'shopware.product.stock_filter' => AbstractStockUpdateFilter::class,
        'shopware.product_export.provider' => AbstractAgenticCommerceProductExportProvider::class,
        'shopware.product_export.validator' => ValidatorInterface::class,
        'shopware.route_scope' => AbstractRouteScope::class,
        'shopware.route_scope_whitelist' => RouteScopeWhitelistInterface::class,
        'shopware.rule.definition' => \Shopware\Core\Framework\Rule\Rule::class,
        'shopware.scheduled.task' => ScheduledTask::class,
        'shopware.seo_url.route' => SeoUrlRouteInterface::class,
        'shopware.sitemap.config_handler' => ConfigHandlerInterface::class,
        'shopware.sitemap_url_provider' => AbstractUrlProvider::class,
        'shopware.snippet.filter' => SnippetFilterInterface::class,
        /** @phpstan-ignore phpat.restrictNamespacesInCore (only class constant is used) */
        'shopware.storefront.captcha' => AbstractCaptcha::class,
        'shopware.sync.fk_resolver' => AbstractFkResolver::class,
        'shopware.system_check' => BaseCheck::class,
        'shopware.tax.provider' => AbstractTaxProvider::class,
        'shopware.telemetry.periodic_metric_collector' => PeriodicMetricCollectorInterface::class,
        'shopware.twig.hierarchy_builder' => TemplateNamespaceHierarchyBuilderInterface::class,
        'shopware.value_generator_connector' => AbstractIncrementStorage::class,
        'shopware.value_generator_pattern' => AbstractValueGenerator::class,
        /** @phpstan-ignore phpat.restrictNamespacesInCore (only class constant is used) */
        'storefront.media.upload.validator' => StorefrontMediaValidatorInterface::class,
        'tax.rule_type_filter' => TaxRuleTypeFilterInterface::class,
    ];

    /**
     * @var array<string, list<ServiceDefinition>>|null
     */
    private ?array $servicesByClass = null;

    /**
     * @var array<string, list<array{tag: string, argument: int|string, kind: 'iterator'|'locator'}>>|null
     */
    private ?array $taggedArgumentsByServiceId = null;

    /**
     * @param array<string, class-string> $additionalTagContracts
     */
    public function __construct(
        private readonly ServiceMap $serviceMap,
        private readonly ReflectionProvider $reflectionProvider,
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
     * @return array<string, class-string>
     */
    private function getTagContracts(): array
    {
        return self::TAG_CONTRACTS + $this->additionalTagContracts;
    }

    /**
     * @return list<RuleError>
     */
    private function checkTaggedService(ServiceDefinition $service, ClassReflection $class): array
    {
        $errors = [];

        foreach ($service->getTags() as $tag) {
            /** @phpstan-ignore phpstanApi.method */
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
                $contract
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
            if (\in_array($contract, $collectionTypes, true)) {
                return [];
            }

            return [
                RuleErrorBuilder::message(\sprintf(
                    'Service "%s" injects services tagged with "%s" into parameter $%s, but the parameter is not typed as the configured tag contract "%s".',
                    $serviceId,
                    $argument['tag'],
                    $parameter->getName(),
                    $contract
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

    private function isClassCompatibleWithContract(ClassReflection $class, string $contract): bool
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
                    /** @phpstan-ignore phpstanApi.method */
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
