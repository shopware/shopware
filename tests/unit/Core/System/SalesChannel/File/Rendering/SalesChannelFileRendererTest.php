<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\File\Rendering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandler;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Adapter\Twig\Extension\NodeExtension;
use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\NamespaceHierarchyBuilder;
use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\TemplateNamespaceHierarchyBuilderInterface;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Adapter\Twig\TemplateScopeDetector;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\File\Discovery\SalesChannelFile;
use Shopware\Core\System\SalesChannel\File\Event\SalesChannelFileTemplateResolveEvent;
use Shopware\Core\System\SalesChannel\File\Rendering\Extension\SalesChannelFileRenderParametersExtension;
use Shopware\Core\System\SalesChannel\File\Rendering\SalesChannelFileRenderer;
use Shopware\Core\System\SalesChannel\File\Rendering\SalesChannelFileTemplateOverrideLoader;
use Shopware\Core\System\SalesChannel\File\SalesChannelFileTemplateResolver;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;

/**
 * @internal
 */
#[CoversClass(SalesChannelFileRenderer::class)]
class SalesChannelFileRendererTest extends TestCase
{
    public function testOverridesParticipateInShopwareTemplateInheritance(): void
    {
        $templateOverrideLoader = new SalesChannelFileTemplateOverrideLoader();
        $loader = new ChainLoader([
            $templateOverrideLoader,
            new ArrayLoader([
                '@Framework/files/agentic/llms.txt.twig' => '{% block content %}core{% endblock %}',
                '@Ucp/files/agentic/llms.txt.twig' => '{% sw_extends \'files/agentic/llms.txt.twig\' %}{% block content %}plugin + {{ parent() }}{% endblock %}',
            ]),
        ]);
        $twig = new Environment($loader);
        $scopeDetector = $this->createMock(TemplateScopeDetector::class);
        $scopeDetector->method('getScopes')->willReturn([TemplateScopeDetector::DEFAULT_SCOPE]);

        $hierarchyBuilder = new NamespaceHierarchyBuilder([
            new SalesChannelFileRendererTestHierarchyBuilder(['Ucp' => 0, 'Framework' => -1]),
        ]);
        $templateFinder = new TemplateFinder($twig, $loader, '', $hierarchyBuilder, $scopeDetector);

        $twig->addExtension(new NodeExtension($templateFinder, $scopeDetector));

        $seoUrlPlaceholderHandler = $this->createSeoUrlPlaceholderHandler();
        $renderer = new SalesChannelFileRenderer(
            $twig,
            $this->createTemplateResolver($templateFinder, $loader, $hierarchyBuilder),
            $templateOverrideLoader,
            $seoUrlPlaceholderHandler,
            $this->createSalesChannelRepository(),
            $this->createExtensionDispatcher()
        );

        $file = new SalesChannelFile(
            'agentic',
            'llms.txt',
            'files/agentic/llms.txt.twig',
            'text/plain; charset=utf-8',
            'files/agentic/llms.txt.twig',
            [
                'Framework' => '@Framework/files/agentic/llms.txt.twig',
                'Ucp' => '@Ucp/files/agentic/llms.txt.twig',
            ],
        );

        $context = $this->createSalesChannelContext();

        $content = $renderer->render($file, $context, [
            'Ucp' => '{% sw_extends \'files/agentic/llms.txt.twig\' %}{% block content %}merchant plugin + {{ parent() }}{% endblock %}',
            'Framework' => '{% block content %}merchant core{% endblock %}',
        ]);

        static::assertSame('merchant plugin + merchant core', $content);
        static::assertSame('plugin + core', $renderer->render($file, $context));
    }

    public function testRenderEntryIsResolvedThroughTemplateFinderInsteadOfDiscoveredSourceOrder(): void
    {
        $templateOverrideLoader = new SalesChannelFileTemplateOverrideLoader();
        $loader = new ChainLoader([
            $templateOverrideLoader,
            new ArrayLoader([
                '@Framework/files/agentic/llms.txt.twig' => '{% block content %}core{% endblock %}',
                '@Ucp/files/agentic/llms.txt.twig' => '{% sw_extends \'files/agentic/llms.txt.twig\' %}{% block content %}plugin + {{ parent() }}{% endblock %}',
            ]),
        ]);
        $twig = new Environment($loader);
        $templateFinder = $this->createTemplateFinder($twig, $loader);

        $renderer = new SalesChannelFileRenderer(
            $twig,
            $this->createTemplateResolver($templateFinder, $loader),
            $templateOverrideLoader,
            $this->createSeoUrlPlaceholderHandler(),
            $this->createSalesChannelRepository(),
            $this->createExtensionDispatcher()
        );

        $file = new SalesChannelFile(
            'agentic',
            'llms.txt',
            'files/agentic/llms.txt.twig',
            'text/plain; charset=utf-8',
            'files/agentic/llms.txt.twig',
            [
                'Ucp' => '@Ucp/files/agentic/llms.txt.twig',
                'Framework' => '@Framework/files/agentic/llms.txt.twig',
            ],
        );

        static::assertSame('plugin + core', $renderer->render($file, $this->createSalesChannelContext()));
    }

    public function testSalesChannelSpecificTemplateHierarchyIsResolvedBeforeRendering(): void
    {
        $templateOverrideLoader = new SalesChannelFileTemplateOverrideLoader();
        $loader = new ChainLoader([
            $templateOverrideLoader,
            new ArrayLoader([
                '@Framework/files/agentic/llms.txt.twig' => '{% block content %}core{% endblock %}',
                '@Ucp/files/agentic/llms.txt.twig' => '{% sw_extends \'files/agentic/llms.txt.twig\' %}{% block content %}plugin + {{ parent() }}{% endblock %}',
            ]),
        ]);
        $twig = new Environment($loader);
        $hierarchyBuilder = new SalesChannelFileRendererTestMutableHierarchyBuilder(['Framework' => 0]);
        $templateFinder = $this->createTemplateFinder($twig, $loader, $hierarchyBuilder);
        $context = $this->createSalesChannelContext();
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(SalesChannelFileTemplateResolveEvent::class, static function (SalesChannelFileTemplateResolveEvent $event) use ($context, $hierarchyBuilder): void {
            static::assertSame($context->getSalesChannelId(), $event->salesChannelId);

            $hierarchyBuilder->setHierarchy(['Ucp' => 0, 'Framework' => -1]);
        });

        $renderer = new SalesChannelFileRenderer(
            $twig,
            $this->createTemplateResolver($templateFinder, $loader, $hierarchyBuilder, $eventDispatcher),
            $templateOverrideLoader,
            $this->createSeoUrlPlaceholderHandler(),
            $this->createSalesChannelRepository(),
            $this->createExtensionDispatcher()
        );

        $file = new SalesChannelFile(
            'agentic',
            'llms.txt',
            'files/agentic/llms.txt.twig',
            'text/plain; charset=utf-8',
            'files/agentic/llms.txt.twig',
            [],
        );

        $content = $renderer->render($file, $context, [
            'Ucp' => '{% sw_extends \'files/agentic/llms.txt.twig\' %}{% block content %}merchant plugin + {{ parent() }}{% endblock %}',
        ]);

        static::assertSame('merchant plugin + core', $content);
    }

    public function testExtensionProvidedBaseTemplateCanBeExtended(): void
    {
        $templateOverrideLoader = new SalesChannelFileTemplateOverrideLoader();
        $loader = new ChainLoader([
            $templateOverrideLoader,
            new ArrayLoader([
                '@VendorBase/files/agentic/vendor.txt.twig' => '{% block content %}vendor base{% endblock %}',
                '@Ucp/files/agentic/vendor.txt.twig' => '{% sw_extends \'files/agentic/vendor.txt.twig\' %}{% block content %}ucp + {{ parent() }}{% endblock %}',
            ]),
        ]);
        $twig = new Environment($loader);
        $hierarchy = ['Ucp' => 0, 'VendorBase' => -1];
        $templateFinder = $this->createTemplateFinder($twig, $loader, $hierarchy);

        $renderer = new SalesChannelFileRenderer(
            $twig,
            $this->createTemplateResolver($templateFinder, $loader, $hierarchy),
            $templateOverrideLoader,
            $this->createSeoUrlPlaceholderHandler(),
            $this->createSalesChannelRepository(),
            $this->createExtensionDispatcher()
        );

        $file = new SalesChannelFile(
            'agentic',
            'vendor.txt',
            'files/agentic/vendor.txt.twig',
            'text/plain; charset=utf-8',
            'files/agentic/vendor.txt.twig',
            [
                'Ucp' => '@Ucp/files/agentic/vendor.txt.twig',
                'VendorBase' => '@VendorBase/files/agentic/vendor.txt.twig',
            ],
        );

        static::assertSame('ucp + vendor base', $renderer->render($file, $this->createSalesChannelContext()));
    }

    public function testUserProvidedContentIsRenderedThroughDedicatedBlock(): void
    {
        $templateOverrideLoader = new SalesChannelFileTemplateOverrideLoader();
        $loader = new ChainLoader([
            $templateOverrideLoader,
            new ArrayLoader([
                '@Framework/files/agentic/llms.txt.twig' => '{% block content %}core{% block user_provided_content %}{% endblock %}{% endblock %}',
                '@Ucp/files/agentic/llms.txt.twig' => '{% sw_extends \'files/agentic/llms.txt.twig\' %}{% block content %}plugin + {{ parent() }}{% endblock %}',
            ]),
        ]);
        $twig = new Environment($loader);
        $scopeDetector = $this->createMock(TemplateScopeDetector::class);
        $scopeDetector->method('getScopes')->willReturn([TemplateScopeDetector::DEFAULT_SCOPE]);

        $hierarchyBuilder = new NamespaceHierarchyBuilder([
            new SalesChannelFileRendererTestHierarchyBuilder(['Ucp' => 0, 'Framework' => -1]),
        ]);
        $templateFinder = new TemplateFinder($twig, $loader, '', $hierarchyBuilder, $scopeDetector);

        $twig->addExtension(new NodeExtension($templateFinder, $scopeDetector));

        $seoUrlPlaceholderHandler = $this->createSeoUrlPlaceholderHandler();
        $renderer = new SalesChannelFileRenderer(
            $twig,
            $this->createTemplateResolver($templateFinder, $loader, $hierarchyBuilder),
            $templateOverrideLoader,
            $seoUrlPlaceholderHandler,
            $this->createSalesChannelRepository(),
            $this->createExtensionDispatcher()
        );

        $file = new SalesChannelFile(
            'agentic',
            'llms.txt',
            'files/agentic/llms.txt.twig',
            'text/plain; charset=utf-8',
            'files/agentic/llms.txt.twig',
            [
                'Framework' => '@Framework/files/agentic/llms.txt.twig',
                'Ucp' => '@Ucp/files/agentic/llms.txt.twig',
            ],
        );

        $context = $this->createSalesChannelContext();

        $content = $renderer->render($file, $context, [
            'user_provided_content' => '{{ salesChannel.name }} must stay literal.',
        ]);

        static::assertSame('plugin + core{{ salesChannel.name }} must stay literal.', $content);
    }

    public function testSeoUrlPlaceholdersAreReplacedAfterRendering(): void
    {
        $templateOverrideLoader = new SalesChannelFileTemplateOverrideLoader();
        $placeholder = SeoUrlPlaceholderHandler::DOMAIN_PLACEHOLDER . '/search#';
        $loader = new ChainLoader([
            $templateOverrideLoader,
            new ArrayLoader([
                '@Framework/files/agentic/llms.txt.twig' => 'Search: ' . $placeholder,
            ]),
        ]);
        $twig = new Environment($loader);
        $scopeDetector = $this->createMock(TemplateScopeDetector::class);
        $scopeDetector->method('getScopes')->willReturn([TemplateScopeDetector::DEFAULT_SCOPE]);

        $hierarchyBuilder = new NamespaceHierarchyBuilder([
            new SalesChannelFileRendererTestHierarchyBuilder(['Framework' => 0]),
        ]);
        $templateFinder = new TemplateFinder($twig, $loader, '', $hierarchyBuilder, $scopeDetector);

        $twig->addExtension(new NodeExtension($templateFinder, $scopeDetector));

        $context = $this->createSalesChannelContext();

        $seoUrlPlaceholderHandler = $this->createMock(SeoUrlPlaceholderHandlerInterface::class);
        $seoUrlPlaceholderHandler
            ->expects($this->once())
            ->method('replace')
            ->with('Search: ' . $placeholder, '', $context)
            ->willReturn('Search: /search');

        $renderer = new SalesChannelFileRenderer(
            $twig,
            $this->createTemplateResolver($templateFinder, $loader, $hierarchyBuilder),
            $templateOverrideLoader,
            $seoUrlPlaceholderHandler,
            $this->createSalesChannelRepository(),
            $this->createExtensionDispatcher()
        );

        $file = new SalesChannelFile(
            'agentic',
            'llms.txt',
            'files/agentic/llms.txt.twig',
            'text/plain; charset=utf-8',
            'files/agentic/llms.txt.twig',
            [
                'Framework' => '@Framework/files/agentic/llms.txt.twig',
            ],
        );

        static::assertSame('Search: /search', $renderer->render($file, $context));
    }

    public function testSalesChannelIsReloadedWithLanguagesAndCurrenciesForTwig(): void
    {
        $templateOverrideLoader = new SalesChannelFileTemplateOverrideLoader();
        $loader = new ChainLoader([
            $templateOverrideLoader,
            new ArrayLoader([
                '@Framework/files/agentic/llms.txt.twig' => '{{ salesChannel.name }}: {{ salesChannel.languages|length }}/{{ salesChannel.currencies|length }}',
            ]),
        ]);
        $twig = new Environment($loader);
        $scopeDetector = $this->createMock(TemplateScopeDetector::class);
        $scopeDetector->method('getScopes')->willReturn([TemplateScopeDetector::DEFAULT_SCOPE]);

        $hierarchyBuilder = new NamespaceHierarchyBuilder([
            new SalesChannelFileRendererTestHierarchyBuilder(['Framework' => 0]),
        ]);
        $templateFinder = new TemplateFinder($twig, $loader, '', $hierarchyBuilder, $scopeDetector);

        $twig->addExtension(new NodeExtension($templateFinder, $scopeDetector));

        $contextSalesChannel = $this->createSalesChannel('Context sales channel');
        $context = $this->createSalesChannelContext($contextSalesChannel);

        $reloadedSalesChannel = $this->createSalesChannel(
            'Reloaded sales channel',
            new LanguageCollection([$this->createLanguage()]),
            new CurrencyCollection([$this->createCurrency()])
        );

        $salesChannelRepository = $this->createSalesChannelRepository(
            $reloadedSalesChannel,
            static function (Criteria $criteria) use ($contextSalesChannel): void {
                static::assertSame([$contextSalesChannel->getId()], $criteria->getIds());

                $associations = $criteria->getAssociations();
                static::assertArrayHasKey('languages', $associations);
                static::assertArrayHasKey('translationCode', $associations['languages']->getAssociations());
                static::assertArrayHasKey('currencies', $associations);
                static::assertArrayHasKey('domains', $associations);
            }
        );

        $renderer = new SalesChannelFileRenderer(
            $twig,
            $this->createTemplateResolver($templateFinder, $loader, $hierarchyBuilder),
            $templateOverrideLoader,
            $this->createSeoUrlPlaceholderHandler(),
            $salesChannelRepository,
            $this->createExtensionDispatcher()
        );

        $file = new SalesChannelFile(
            'agentic',
            'llms.txt',
            'files/agentic/llms.txt.twig',
            'text/plain; charset=utf-8',
            'files/agentic/llms.txt.twig',
            [
                'Framework' => '@Framework/files/agentic/llms.txt.twig',
            ],
        );

        static::assertSame('Reloaded sales channel: 1/1', $renderer->render($file, $context));
    }

    public function testDefaultParametersDoNotCreateFileSpecificContext(): void
    {
        $templateOverrideLoader = new SalesChannelFileTemplateOverrideLoader();
        $loader = new ChainLoader([
            $templateOverrideLoader,
            new ArrayLoader([
                '@Framework/files/agentic/.well-known/ai-catalog.json.twig' => '{{ salesChannelFileContext|default("none") }}',
            ]),
        ]);
        $twig = new Environment($loader);
        $templateFinder = $this->createTemplateFinder($twig, $loader, ['Framework' => 0]);

        $renderer = new SalesChannelFileRenderer(
            $twig,
            $this->createTemplateResolver($templateFinder, $loader, ['Framework' => 0]),
            $templateOverrideLoader,
            $this->createSeoUrlPlaceholderHandler(),
            $this->createSalesChannelRepository(),
            $this->createExtensionDispatcher()
        );

        $file = new SalesChannelFile(
            'agentic',
            '.well-known/ai-catalog.json',
            'files/agentic/.well-known/ai-catalog.json.twig',
            'application/json; charset=utf-8',
            'files/agentic/.well-known/ai-catalog.json.twig',
            [
                'Framework' => '@Framework/files/agentic/.well-known/ai-catalog.json.twig',
            ],
        );

        static::assertSame(
            'none',
            $renderer->render($file, $this->createSalesChannelContext())
        );
    }

    public function testRenderParametersCanBeExtendedForSpecificFile(): void
    {
        $templateOverrideLoader = new SalesChannelFileTemplateOverrideLoader();
        $loader = new ChainLoader([
            $templateOverrideLoader,
            new ArrayLoader([
                '@Framework/files/agentic/llms.txt.twig' => '{{ customAgenticValue }}',
            ]),
        ]);
        $twig = new Environment($loader);
        $templateFinder = $this->createTemplateFinder($twig, $loader, ['Framework' => 0]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(SalesChannelFileRenderParametersExtension::onPost(), static function (SalesChannelFileRenderParametersExtension $extension): void {
            if ($extension->file->fileName !== 'llms.txt') {
                return;
            }

            \assert(\is_array($extension->result));
            $extension->result['customAgenticValue'] = 'extended';
        });

        $renderer = new SalesChannelFileRenderer(
            $twig,
            $this->createTemplateResolver($templateFinder, $loader, ['Framework' => 0]),
            $templateOverrideLoader,
            $this->createSeoUrlPlaceholderHandler(),
            $this->createSalesChannelRepository(),
            $this->createExtensionDispatcher($dispatcher)
        );

        $file = new SalesChannelFile(
            'agentic',
            'llms.txt',
            'files/agentic/llms.txt.twig',
            'text/plain; charset=utf-8',
            'files/agentic/llms.txt.twig',
            [
                'Framework' => '@Framework/files/agentic/llms.txt.twig',
            ],
        );

        static::assertSame('extended', $renderer->render($file, $this->createSalesChannelContext()));
    }

    private function createSeoUrlPlaceholderHandler(): SeoUrlPlaceholderHandlerInterface&MockObject
    {
        $seoUrlPlaceholderHandler = $this->createMock(SeoUrlPlaceholderHandlerInterface::class);
        $seoUrlPlaceholderHandler
            ->method('replace')
            ->willReturnArgument(0);

        return $seoUrlPlaceholderHandler;
    }

    /**
     * @param array<string, int> $hierarchy
     */
    private function createTemplateFinder(
        Environment $twig,
        ChainLoader $loader,
        array|TemplateNamespaceHierarchyBuilderInterface $hierarchy = ['Ucp' => 0, 'Framework' => -1]
    ): TemplateFinder {
        $scopeDetector = $this->createMock(TemplateScopeDetector::class);
        $scopeDetector->method('getScopes')->willReturn([TemplateScopeDetector::DEFAULT_SCOPE]);

        $templateFinder = new TemplateFinder(
            $twig,
            $loader,
            '',
            $this->createNamespaceHierarchyBuilder($hierarchy),
            $scopeDetector,
        );

        $twig->addExtension(new NodeExtension($templateFinder, $scopeDetector));

        return $templateFinder;
    }

    /**
     * @param array<string, int> $hierarchy
     */
    private function createTemplateResolver(
        TemplateFinder $templateFinder,
        ChainLoader $loader,
        NamespaceHierarchyBuilder|array|TemplateNamespaceHierarchyBuilderInterface $hierarchy = ['Ucp' => 0, 'Framework' => -1],
        ?EventDispatcher $eventDispatcher = null
    ): SalesChannelFileTemplateResolver {
        return new SalesChannelFileTemplateResolver(
            $templateFinder,
            $this->createNamespaceHierarchyBuilder($hierarchy),
            $loader,
            $eventDispatcher ?? new EventDispatcher(),
        );
    }

    /**
     * @param array<string, int> $hierarchy
     */
    private function createNamespaceHierarchyBuilder(NamespaceHierarchyBuilder|array|TemplateNamespaceHierarchyBuilderInterface $hierarchy): NamespaceHierarchyBuilder
    {
        if ($hierarchy instanceof NamespaceHierarchyBuilder) {
            return $hierarchy;
        }

        return new NamespaceHierarchyBuilder([
            \is_array($hierarchy) ? new SalesChannelFileRendererTestHierarchyBuilder($hierarchy) : $hierarchy,
        ]);
    }

    private function createSalesChannelContext(?SalesChannelEntity $salesChannel = null, ?string $domainId = null): SalesChannelContext&MockObject
    {
        $salesChannel ??= $this->createSalesChannel('Context sales channel');
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn($salesChannel->getId());
        $context->method('getSalesChannel')->willReturn($salesChannel);
        $context->method('getContext')->willReturn(Context::createDefaultContext());
        $context->method('getDomainId')->willReturn($domainId);

        return $context;
    }

    /**
     * @param \Closure(Criteria, Context): void|null $criteriaAssertion
     *
     * @return EntityRepository<SalesChannelCollection>&MockObject
     */
    private function createSalesChannelRepository(?SalesChannelEntity $salesChannel = null, ?\Closure $criteriaAssertion = null): EntityRepository&MockObject
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository
            ->method('search')
            ->willReturnCallback(static function (Criteria $criteria, Context $context) use ($salesChannel, $criteriaAssertion): EntitySearchResult {
                if ($criteriaAssertion !== null) {
                    $criteriaAssertion($criteria, $context);
                }

                return new EntitySearchResult(
                    SalesChannelDefinition::ENTITY_NAME,
                    $salesChannel instanceof SalesChannelEntity ? 1 : 0,
                    new SalesChannelCollection($salesChannel instanceof SalesChannelEntity ? [$salesChannel] : []),
                    null,
                    $criteria,
                    $context
                );
            });

        return $repository;
    }

    private function createExtensionDispatcher(?EventDispatcher $eventDispatcher = null): ExtensionDispatcher
    {
        return new ExtensionDispatcher($eventDispatcher ?? new EventDispatcher());
    }

    private function createSalesChannel(
        string $name,
        ?LanguageCollection $languages = null,
        ?CurrencyCollection $currencies = null,
    ): SalesChannelEntity {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());
        $salesChannel->setName($name);

        if ($languages !== null) {
            $salesChannel->setLanguages($languages);
        }

        if ($currencies !== null) {
            $salesChannel->setCurrencies($currencies);
        }

        return $salesChannel;
    }

    private function createLanguage(): LanguageEntity
    {
        $language = new LanguageEntity();
        $language->setId(Uuid::randomHex());
        $language->setName('English');

        return $language;
    }

    private function createCurrency(): CurrencyEntity
    {
        $currency = new CurrencyEntity();
        $currency->setId(Uuid::randomHex());
        $currency->setIsoCode('EUR');
        $currency->setName('Euro');

        return $currency;
    }
}

/**
 * @internal
 */
final readonly class SalesChannelFileRendererTestHierarchyBuilder implements TemplateNamespaceHierarchyBuilderInterface
{
    /**
     * @param array<string, int> $hierarchy
     */
    public function __construct(private array $hierarchy)
    {
    }

    public function buildNamespaceHierarchy(array $namespaceHierarchy): array
    {
        return $this->hierarchy + $namespaceHierarchy;
    }
}

/**
 * @internal
 */
final class SalesChannelFileRendererTestMutableHierarchyBuilder implements TemplateNamespaceHierarchyBuilderInterface
{
    /**
     * @param array<string, int> $hierarchy
     */
    public function __construct(private array $hierarchy)
    {
    }

    /**
     * @param array<string, int> $hierarchy
     */
    public function setHierarchy(array $hierarchy): void
    {
        $this->hierarchy = $hierarchy;
    }

    public function buildNamespaceHierarchy(array $namespaceHierarchy): array
    {
        return $this->hierarchy + $namespaceHierarchy;
    }
}
