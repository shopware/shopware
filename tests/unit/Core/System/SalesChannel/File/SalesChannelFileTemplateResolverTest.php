<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\File;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\NamespaceHierarchyBuilder;
use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\TemplateNamespaceHierarchyBuilderInterface;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Adapter\Twig\TemplateScopeDetector;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\File\Discovery\SalesChannelFile;
use Shopware\Core\System\SalesChannel\File\Event\SalesChannelFileTemplateResolveEvent;
use Shopware\Core\System\SalesChannel\File\SalesChannelFileTemplateResolver;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SalesChannelFileTemplateResolver::class)]
class SalesChannelFileTemplateResolverTest extends TestCase
{
    public function testItUsesExtensionOwnedBaseTemplateFromResolvedChain(): void
    {
        $resolver = $this->createResolver([
            '@VendorBase/files/agentic/llms.txt.twig' => '{% block content %}vendor base{% endblock %}',
            '@Ucp/files/agentic/llms.txt.twig' => '{% sw_extends \'files/agentic/llms.txt.twig\' %}{% block content %}ucp + {{ parent() }}{% endblock %}',
        ], ['Ucp' => 0, 'VendorBase' => -1, 'Framework' => -2]);

        $file = $this->createSalesChannelFile([
            'Ucp' => '@Ucp/files/agentic/llms.txt.twig',
            'VendorBase' => '@VendorBase/files/agentic/llms.txt.twig',
        ]);

        static::assertSame('@VendorBase/files/agentic/llms.txt.twig', $resolver->getBaseTemplateName($file));
        static::assertSame('@Ucp/files/agentic/llms.txt.twig', $resolver->getRenderTemplateName($file));
    }

    public function testItUsesResolvedChainInsteadOfParsingTemplateSource(): void
    {
        $resolver = $this->createResolver([
            '@Framework/files/agentic/llms.txt.twig' => '{# {% extends "ignored.html.twig" %} #}{% set example = "{% sw_extends ignored %}" %}{% block content %}core{% endblock %}',
            '@Ucp/files/agentic/llms.txt.twig' => '{% sw_extends \'files/agentic/llms.txt.twig\' %}{% block content %}ucp + {{ parent() }}{% endblock %}',
        ], ['Ucp' => 0, 'Framework' => -1]);

        $file = $this->createSalesChannelFile([
            'Ucp' => '@Ucp/files/agentic/llms.txt.twig',
            'Framework' => '@Framework/files/agentic/llms.txt.twig',
        ]);

        static::assertSame('@Framework/files/agentic/llms.txt.twig', $resolver->getBaseTemplateName($file));
        static::assertSame('@Ucp/files/agentic/llms.txt.twig', $resolver->getRenderTemplateName($file));
    }

    public function testItResolvesTemplateChainForSalesChannelContext(): void
    {
        $hierarchyBuilder = new SalesChannelFileTemplateResolverTestMutableHierarchyBuilder(['Framework' => 0]);
        $dispatcher = new EventDispatcher();
        $salesChannelId = 'sales-channel-id';
        $dispatcher->addListener(SalesChannelFileTemplateResolveEvent::class, static function (SalesChannelFileTemplateResolveEvent $event) use ($hierarchyBuilder, $salesChannelId): void {
            static::assertSame($salesChannelId, $event->salesChannelId);

            $hierarchyBuilder->setHierarchy(['Ucp' => 0, 'Framework' => -1]);
        });

        $resolver = $this->createResolverWithHierarchyBuilder([
            '@Framework/files/agentic/llms.txt.twig' => '{% block content %}core{% endblock %}',
            '@Ucp/files/agentic/llms.txt.twig' => '{% sw_extends \'files/agentic/llms.txt.twig\' %}{% block content %}ucp + {{ parent() }}{% endblock %}',
        ], $hierarchyBuilder, $dispatcher);

        $file = $this->createSalesChannelFile([]);

        static::assertSame('@Framework/files/agentic/llms.txt.twig', $resolver->getBaseTemplateName($file));
        static::assertSame('@Ucp/files/agentic/llms.txt.twig', $resolver->getRenderTemplateName($file, $salesChannelId));
    }

    /**
     * @param array<string, string> $templates
     * @param array<string, int> $hierarchy
     */
    private function createResolver(array $templates, array $hierarchy): SalesChannelFileTemplateResolver
    {
        return $this->createResolverWithHierarchyBuilder(
            $templates,
            new SalesChannelFileTemplateResolverTestHierarchyBuilder($hierarchy),
            new EventDispatcher(),
        );
    }

    /**
     * @param array<string, string> $templates
     */
    private function createResolverWithHierarchyBuilder(
        array $templates,
        TemplateNamespaceHierarchyBuilderInterface $hierarchyBuilder,
        ?EventDispatcherInterface $eventDispatcher = null
    ): SalesChannelFileTemplateResolver {
        $loader = new ArrayLoader($templates);
        $twig = new Environment($loader);
        $scopeDetector = $this->createMock(TemplateScopeDetector::class);
        $scopeDetector->method('getScopes')->willReturn([TemplateScopeDetector::DEFAULT_SCOPE]);

        return new SalesChannelFileTemplateResolver(
            new TemplateFinder(
                $twig,
                $loader,
                '',
                new NamespaceHierarchyBuilder([
                    $hierarchyBuilder,
                ]),
                $scopeDetector,
            ),
            new NamespaceHierarchyBuilder([
                $hierarchyBuilder,
            ]),
            $loader,
            $eventDispatcher ?? new EventDispatcher(),
        );
    }

    /**
     * @param array<string, string> $templates
     */
    private function createSalesChannelFile(array $templates): SalesChannelFile
    {
        return new SalesChannelFile(
            'agentic',
            'llms.txt',
            'files/agentic/llms.txt.twig',
            'text/plain; charset=utf-8',
            'files/agentic/llms.txt.twig',
            $templates,
        );
    }
}

/**
 * @internal
 */
final readonly class SalesChannelFileTemplateResolverTestHierarchyBuilder implements TemplateNamespaceHierarchyBuilderInterface
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
final class SalesChannelFileTemplateResolverTestMutableHierarchyBuilder implements TemplateNamespaceHierarchyBuilderInterface
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
