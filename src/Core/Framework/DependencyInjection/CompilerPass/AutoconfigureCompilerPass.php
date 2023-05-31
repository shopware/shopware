<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\CartValidatorInterface;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupPackagerInterface;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupSorterInterface;
use Shopware\Core\Checkout\Cart\LineItemFactoryHandler\LineItemFactoryInterface;
use Shopware\Core\Checkout\Cart\TaxProvider\AbstractTaxProvider;
use Shopware\Core\Checkout\Customer\Password\LegacyEncoder\LegacyEncoderInterface;
use Shopware\Core\Checkout\Document\Renderer\AbstractDocumentRenderer;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\SynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\FilterPickerInterface;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\FilterSorterInterface;
use Shopware\Core\Content\Cms\DataResolver\Element\CmsElementResolverInterface;
use Shopware\Core\Content\Flow\Dispatching\Storer\FlowStorer;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Shopware\Core\Content\Sitemap\Provider\AbstractUrlProvider;
use Shopware\Core\Framework\Adapter\Filesystem\Adapter\AdapterFactoryInterface;
use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\TemplateNamespaceHierarchyBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\FieldSerializerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Shopware\Core\Framework\Routing\AbstractRouteScope;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\AbstractValueGenerator;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\Tax\TaxRuleType\TaxRuleTypeFilterInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[Package('core')]
class AutoconfigureCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $container
            ->registerForAutoconfiguration(EntityDefinition::class)
            ->addTag('shopware.entity.definition');

        $container
            ->registerForAutoconfiguration(SalesChannelDefinition::class)
            ->addTag('shopware.sales_channel.entity.definition');

        $container
            ->registerForAutoconfiguration(AbstractRouteScope::class)
            ->addTag('shopware.route_scope');

        $container
            ->registerForAutoconfiguration(EntityExtension::class)
            ->addTag('shopware.entity.extension');

        $container
            ->registerForAutoconfiguration(CartProcessorInterface::class)
            ->addTag('shopware.cart.processor');

        $container
            ->registerForAutoconfiguration(CartDataCollectorInterface::class)
            ->addTag('shopware.cart.collector');

        $container
            ->registerForAutoconfiguration(ScheduledTask::class)
            ->addTag('shopware.scheduled.task');

        $container
            ->registerForAutoconfiguration(CartValidatorInterface::class)
            ->addTag('shopware.cart.validator');

        $container
            ->registerForAutoconfiguration(LineItemFactoryInterface::class)
            ->addTag('shopware.cart.line_item.factory');

        $container
            ->registerForAutoconfiguration(LineItemGroupPackagerInterface::class)
            ->addTag('lineitem.group.packager');

        $container
            ->registerForAutoconfiguration(LineItemGroupSorterInterface::class)
            ->addTag('lineitem.group.sorter');

        $container
            ->registerForAutoconfiguration(LegacyEncoderInterface::class)
            ->addTag('shopware.legacy_encoder');

        $container
            ->registerForAutoconfiguration(EntityIndexer::class)
            ->addTag('shopware.entity_indexer');

        $container
            ->registerForAutoconfiguration(ExceptionHandlerInterface::class)
            ->addTag('shopware.dal.exception_handler');

        $container
            ->registerForAutoconfiguration(AbstractDocumentRenderer::class)
            ->addTag('document.renderer');

        $container
            ->registerForAutoconfiguration(SynchronousPaymentHandlerInterface::class)
            ->addTag('shopware.payment.method.sync');

        $container
            ->registerForAutoconfiguration(FilterSorterInterface::class)
            ->addTag('promotion.filter.sorter');

        $container
            ->registerForAutoconfiguration(FilterPickerInterface::class)
            ->addTag('promotion.filter.picker');

        $container
            ->registerForAutoconfiguration(Rule::class)
            ->addTag('shopware.rule.definition');

        $container
            ->registerForAutoconfiguration(AbstractTaxProvider::class)
            ->addTag('shopware.tax.provider');

        $container
            ->registerForAutoconfiguration(CmsElementResolverInterface::class)
            ->addTag('shopware.cms.data_resolver');

        $container
            ->registerForAutoconfiguration(FieldSerializerInterface::class)
            ->addTag('shopware.field_serializer');

        $container
            ->registerForAutoconfiguration(FlowStorer::class)
            ->addTag('flow.storer');

        $container
            ->registerForAutoconfiguration(AbstractUrlProvider::class)
            ->addTag('shopware.sitemap_url_provider');

        $container
            ->registerForAutoconfiguration(AdapterFactoryInterface::class)
            ->addTag('shopware.filesystem.factory');

        $container
            ->registerForAutoconfiguration(AbstractValueGenerator::class)
            ->addTag('shopware.value_generator_pattern');

        $container
            ->registerForAutoconfiguration(TaxRuleTypeFilterInterface::class)
            ->addTag('tax.rule_type_filter');

        $container
            ->registerForAutoconfiguration(SeoUrlRouteInterface::class)
            ->addTag('shopware.seo_url.route');

        $container
            ->registerForAutoconfiguration(TemplateNamespaceHierarchyBuilderInterface::class)
            ->addTag('shopware.twig.hierarchy_builder');

        $container->registerAliasForArgument('shopware.filesystem.private', FilesystemOperator::class, 'privateFilesystem');
        $container->registerAliasForArgument('shopware.filesystem.public', FilesystemOperator::class, 'publicFilesystem');
    }
}
