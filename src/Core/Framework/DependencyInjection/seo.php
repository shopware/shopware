<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Shopware\Core\Content\Category\Service\CategoryUrlGenerator;
use Shopware\Core\Content\LandingPage\LandingPageDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Seo\Api\SeoActionController;
use Shopware\Core\Content\Seo\EmptyPathInfoResolver;
use Shopware\Core\Content\Seo\HreflangLoader;
use Shopware\Core\Content\Seo\HreflangLoaderInterface;
use Shopware\Core\Content\Seo\MainCategory\MainCategoryDefinition;
use Shopware\Core\Content\Seo\MainCategory\SalesChannel\SalesChannelMainCategoryDefinition;
use Shopware\Core\Content\Seo\SalesChannel\SeoUrlRoute as SalesChannelSeoUrlRoute;
use Shopware\Core\Content\Seo\SalesChannel\StoreApiSeoResolver;
use Shopware\Core\Content\Seo\SeoResolver;
use Shopware\Core\Content\Seo\SeoUrl\SalesChannel\SalesChannelSeoUrlDefinition;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlDefinition;
use Shopware\Core\Content\Seo\SeoUrlGenerator;
use Shopware\Core\Content\Seo\SeoUrlPersister;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandler;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Content\Seo\SeoUrlRoute\CategoryStoreApiUrlRoute;
use Shopware\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Shopware\Core\Content\Seo\SeoUrlRoute\LandingPageStoreApiUrlRoute;
use Shopware\Core\Content\Seo\SeoUrlRoute\ProductStoreApiUrlRoute;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Shopware\Core\Content\Seo\SeoUrlRoute\StoreApiSeoUrlUpdateListener;
use Shopware\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateChangeSubscriber;
use Shopware\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateDefinition;
use Shopware\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateIndexingHandler;
use Shopware\Core\Content\Seo\SeoUrlTwigFactory;
use Shopware\Core\Content\Seo\SeoUrlUpdater;
use Shopware\Core\Content\Seo\Validation\Constraint\ValidSeoPathInfoValidator;
use Shopware\Core\Content\Seo\Validation\SeoUrlValidationFactory;
use Shopware\Core\Content\Seo\Validation\SeoUrlWriteValidator;
use Shopware\Core\Framework\Adapter\Twig\Extension\BuildBreadcrumbExtension;
use Shopware\Core\Framework\Adapter\Twig\Extension\CategoryUrlExtension;
use Shopware\Core\Framework\Adapter\Twig\Extension\EntitySeoUrlFunctionExtension;
use Shopware\Core\Framework\Adapter\Twig\Extension\MediaExtension;
use Shopware\Core\Framework\Adapter\Twig\Extension\RawUrlFunctionExtension;
use Shopware\Core\Framework\Adapter\Twig\Extension\SeoUrlFunctionExtension;
use Shopware\Core\Framework\Adapter\Twig\Extension\SwSanitizeTwigFilter;
use Shopware\Core\Framework\Adapter\Twig\Extension\TwigFeaturesWithInheritanceExtension;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Adapter\Twig\TwigVariableParserFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Twig\Environment;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(SeoUrlDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(SalesChannelSeoUrlDefinition::class)
        ->tag('shopware.sales_channel.entity.definition');

    $services->set(SeoUrlTemplateDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(MainCategoryDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(SalesChannelMainCategoryDefinition::class)
        ->tag('shopware.sales_channel.entity.definition');

    $services->set(SeoUrlGenerator::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service('router.default'),
            service('request_stack'),
            service('shopware.seo_url.twig'),
            service(TwigVariableParserFactory::class),
            service('logger'),
        ]);

    $services->set(SeoUrlPersister::class)
        ->args([
            service(Connection::class),
            service('seo_url.repository'),
            service('event_dispatcher'),
            service(ClockInterface::class),
        ]);

    $services->set(SeoUrlRouteRegistry::class)
        ->lazy()
        ->args([
            tagged_iterator('shopware.seo_url.route'),
        ]);

    $services->set(ProductStoreApiUrlRoute::class)
        ->args([
            service(ProductDefinition::class),
        ])
        ->tag('shopware.entity.seo_url.route');

    $services->set(CategoryStoreApiUrlRoute::class)
        ->args([
            service(CategoryDefinition::class),
        ])
        ->tag('shopware.entity.seo_url.route');

    $services->set(LandingPageStoreApiUrlRoute::class)
        ->args([
            service(LandingPageDefinition::class),
        ])
        ->tag('shopware.entity.seo_url.route');

    $services->set(EntityRouteResolver::class)
        ->args([
            service(SeoUrlRouteRegistry::class),
            service(SeoUrlPlaceholderHandlerInterface::class),
            service('router'),
            tagged_iterator('shopware.entity.seo_url.route'),
        ]);

    $services->set(EmptyPathInfoResolver::class)
        ->public()
        ->decorate(SeoResolver::class, null, -2000)
        ->args([
            service(EmptyPathInfoResolver::class . '.inner'),
        ]);

    $services->set(SeoResolver::class)
        ->public()
        ->args([
            service(Connection::class),
        ]);

    $services->set(SeoActionController::class)
        ->public()
        ->args([
            service(SeoUrlGenerator::class),
            service(SeoUrlPersister::class),
            service(DefinitionInstanceRegistry::class),
            service(SeoUrlRouteRegistry::class),
            service(SeoUrlValidationFactory::class),
            service(DataValidator::class),
            service('sales_channel.repository'),
            service(RequestCriteriaBuilder::class),
            service(DefinitionInstanceRegistry::class),
            service(EntityRouteResolver::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(SeoUrlValidationFactory::class);

    $services->set(ValidSeoPathInfoValidator::class)
        ->tag('validator.constraint_validator');

    $services->set(SeoUrlWriteValidator::class)
        ->tag('kernel.event_subscriber');

    $services->set(SeoUrlFunctionExtension::class)
        ->args([
            service('twig.extension.routing'),
            service(SeoUrlPlaceholderHandlerInterface::class),
        ])
        ->tag('twig.extension');

    $services->set(EntitySeoUrlFunctionExtension::class)
        ->args([
            service(EntityRouteResolver::class),
        ])
        ->tag('twig.extension');

    $services->set(TwigFeaturesWithInheritanceExtension::class)
        ->args([
            service(TemplateFinder::class),
        ])
        ->tag('twig.extension');

    $services->set(CategoryUrlExtension::class)
        ->args([
            service('twig.extension.routing'),
            service(CategoryUrlGenerator::class),
        ])
        ->tag('twig.extension');

    $services->set(SeoUrlPlaceholderHandlerInterface::class, SeoUrlPlaceholderHandler::class)
        ->public()
        ->args([
            service('request_stack'),
            service('router.default'),
            service(Connection::class),
        ]);

    $services->set(MediaExtension::class)
        ->args([
            service('media.repository'),
        ])
        ->tag('twig.extension');

    $services->set(RawUrlFunctionExtension::class)
        ->args([
            service('router'),
            service('request_stack'),
        ])
        ->tag('twig.extension');

    $services->set(SwSanitizeTwigFilter::class)
        ->args([
            service(HtmlSanitizer::class),
        ])
        ->tag('twig.extension')
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(HreflangLoaderInterface::class, HreflangLoader::class)
        ->args([
            service('router.default'),
            service(Connection::class),
        ]);

    $services->set(SalesChannelSeoUrlRoute::class)
        ->public()
        ->args([
            service('sales_channel.seo_url.repository'),
        ]);

    $services->set(StoreApiSeoResolver::class)
        ->args([
            service('sales_channel.seo_url.repository'),
            service(DefinitionInstanceRegistry::class),
            service(SalesChannelDefinitionInstanceRegistry::class),
            service(SeoUrlRouteRegistry::class),
            service(EntityRouteResolver::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(SeoUrlUpdater::class)
        ->args([
            service('language.repository'),
            service(SeoUrlRouteRegistry::class),
            service(SeoUrlGenerator::class),
            service(SeoUrlPersister::class),
            service(Connection::class),
            service('sales_channel.repository'),
            tagged_iterator('shopware.entity.seo_url.route'),
        ]);

    $services->set(StoreApiSeoUrlUpdateListener::class)
        ->args([
            service(SeoUrlUpdater::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(SeoUrlTemplateChangeSubscriber::class)
        ->args([
            service(Connection::class),
            service('messenger.default_bus'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(SeoUrlTemplateIndexingHandler::class)
        ->args([
            service(SeoUrlUpdater::class),
            service(IteratorFactory::class),
            service(DefinitionInstanceRegistry::class),
            service(SeoUrlRouteRegistry::class),
            service('messenger.default_bus'),
            tagged_iterator('shopware.entity.seo_url.route'),
        ])
        ->tag('messenger.message_handler');

    $services->set(BuildBreadcrumbExtension::class)
        ->args([
            service(CategoryBreadcrumbBuilder::class),
            service('sales_channel.category.repository'),
            service('category.repository'),
        ])
        ->tag('twig.extension');

    $services->set(SeoUrlTwigFactory::class);

    $services->set('shopware.seo_url.twig', Environment::class)
        ->factory([service(SeoUrlTwigFactory::class), 'createTwigEnvironment'])
        ->args([
            service('slugify'),
            tagged_iterator('shopware.seo_url.twig.extension'),
            param('kernel.cache_dir'),
        ]);
};
