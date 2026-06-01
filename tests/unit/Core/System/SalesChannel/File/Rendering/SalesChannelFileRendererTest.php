<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\File\Rendering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\Extension\NodeExtension;
use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\NamespaceHierarchyBuilder;
use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\TemplateNamespaceHierarchyBuilderInterface;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Adapter\Twig\TemplateScopeDetector;
use Shopware\Core\System\SalesChannel\File\Discovery\SalesChannelFile;
use Shopware\Core\System\SalesChannel\File\Rendering\SalesChannelFileRenderer;
use Shopware\Core\System\SalesChannel\File\Rendering\SalesChannelFileTemplateOverrideLoader;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
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
                '@Ucp/files/agentic/llms.txt.twig' => '{% sw_extends \'@Framework/files/agentic/llms.txt.twig\' %}{% block content %}plugin + {{ parent() }}{% endblock %}',
            ]),
        ]);
        $twig = new Environment($loader);
        $scopeDetector = $this->createMock(TemplateScopeDetector::class);
        $scopeDetector->method('getScopes')->willReturn([TemplateScopeDetector::DEFAULT_SCOPE]);

        $hierarchyBuilder = new NamespaceHierarchyBuilder([
            new SalesChannelFileRendererTestHierarchyBuilder(['Ucp' => -10, 'Framework' => 0]),
        ]);
        $templateFinder = new TemplateFinder($twig, $loader, '', $hierarchyBuilder, $scopeDetector);

        $twig->addExtension(new NodeExtension($templateFinder, $scopeDetector));

        $renderer = new SalesChannelFileRenderer($twig, $templateFinder, $templateOverrideLoader);

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

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannel')->willReturn(new SalesChannelEntity());

        $content = $renderer->render($file, $context, [
            'Ucp' => '{% sw_extends \'@Framework/files/agentic/llms.txt.twig\' %}{% block content %}merchant plugin + {{ parent() }}{% endblock %}',
            'Framework' => '{% block content %}merchant core{% endblock %}',
        ]);

        static::assertSame('merchant plugin + merchant core', $content);
        static::assertSame('plugin + core', $renderer->render($file, $context));
    }

    public function testUserProvidedContentIsRenderedThroughDedicatedBlock(): void
    {
        $templateOverrideLoader = new SalesChannelFileTemplateOverrideLoader();
        $loader = new ChainLoader([
            $templateOverrideLoader,
            new ArrayLoader([
                '@Framework/files/agentic/llms.txt.twig' => '{% block content %}core{% block user_provided_content %}{% endblock %}{% endblock %}',
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

        $renderer = new SalesChannelFileRenderer($twig, $templateFinder, $templateOverrideLoader);

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

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannel')->willReturn(new SalesChannelEntity());

        $content = $renderer->render($file, $context, [
            'user_provided_content' => '{{ salesChannel.name }} must stay literal.',
        ]);

        static::assertSame('core{{ salesChannel.name }} must stay literal.', $content);
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
