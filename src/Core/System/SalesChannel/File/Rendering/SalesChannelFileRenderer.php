<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\File\Rendering;

use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\File\Discovery\SalesChannelFile;
use Shopware\Core\System\SalesChannel\File\Rendering\Extension\SalesChannelFileRenderParametersExtension;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Twig\Environment;
use Twig\Error\LoaderError;

/**
 * @internal
 */
#[Package('framework')]
class SalesChannelFileRenderer
{
    private const USER_PROVIDED_CONTENT_OVERRIDE_KEY = 'user_provided_content';

    private const USER_PROVIDED_CONTENT_TEMPLATE = '@SalesChannelFileUserProvidedContent/%s';

    /**
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     */
    public function __construct(
        private readonly Environment $twig,
        private readonly TemplateFinder $templateFinder,
        private readonly SalesChannelFileTemplateOverrideLoader $templateOverrideLoader,
        private readonly SeoUrlPlaceholderHandlerInterface $seoUrlPlaceholderHandler,
        private readonly EntityRepository $salesChannelRepository,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    /**
     * @param array<string, mixed> $templateOverrides
     */
    public function render(SalesChannelFile $file, SalesChannelContext $context, array $templateOverrides = []): string
    {
        $overrideTemplates = $this->buildOverrideTemplates($file, $templateOverrides);
        $parameters = $this->buildParameters($file, $context);
        $templateName = $this->getRenderTemplateName($file);

        $userProvidedContent = $this->getUserProvidedContent($templateOverrides);
        if ($userProvidedContent !== null) {
            $parentTemplateName = $templateName;
            $templateName = \sprintf(self::USER_PROVIDED_CONTENT_TEMPLATE, $file->templatePath);
            $overrideTemplates[$templateName] = $this->buildUserProvidedContentTemplate($userProvidedContent, $parentTemplateName);
        }

        $content = $this->templateOverrideLoader->withTemplateOverrides(
            $overrideTemplates,
            fn (): string => $this->twig->render($templateName, $parameters)
        );

        return $this->seoUrlPlaceholderHandler->replace($content, '', $context);
    }

    private function getRenderTemplateName(SalesChannelFile $file): string
    {
        return $this->templateFinder->find($this->getBaseTemplateName($file));
    }

    private function getBaseTemplateName(SalesChannelFile $file): string
    {
        foreach ($file->templates as $templateName) {
            if (!$this->templateExtendsAnotherTemplate($templateName)) {
                return $templateName;
            }
        }

        return $file->baseTemplateName;
    }

    private function templateExtendsAnotherTemplate(string $templateName): bool
    {
        try {
            $source = $this->twig->getLoader()->getSourceContext($templateName)->getCode();
        } catch (LoaderError) {
            return false;
        }

        // Only detect inheritance markers; Twig still resolves the actual template chain.
        return preg_match('/{%-?\s*(?:sw_)?extends\b/', $source) === 1;
    }

    /**
     * @param array<string, mixed> $templateOverrides
     *
     * @return array<string, string>
     */
    private function buildOverrideTemplates(SalesChannelFile $file, array $templateOverrides): array
    {
        $overrideTemplates = [];

        foreach ($file->templates as $twigNamespace => $templateName) {
            $override = $templateOverrides[$twigNamespace] ?? null;

            if (!\is_string($override)) {
                continue;
            }

            $overrideTemplates[$templateName] = $override;
        }

        return $overrideTemplates;
    }

    /**
     * @param array<string, mixed> $templateOverrides
     */
    private function getUserProvidedContent(array $templateOverrides): ?string
    {
        $userProvidedContent = $templateOverrides[self::USER_PROVIDED_CONTENT_OVERRIDE_KEY] ?? null;

        if (!\is_string($userProvidedContent) || trim($userProvidedContent) === '') {
            return null;
        }

        return $userProvidedContent;
    }

    private function buildUserProvidedContentTemplate(string $userProvidedContent, string $parentTemplateName): string
    {
        $encodedContent = json_encode($userProvidedContent, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        \assert(\is_string($encodedContent));

        // The generated override namespace is not part of the normal namespace hierarchy.
        // Resolve the render entry first, then extend that concrete template.
        return \sprintf(
            "{%% extends '%s' %%}\n\n{%% block user_provided_content %%}{{ %s|raw }}{%% endblock %%}",
            $parentTemplateName,
            $encodedContent
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildParameters(SalesChannelFile $file, SalesChannelContext $context): array
    {
        $salesChannel = $this->loadSalesChannel($context);

        return $this->extensions->publish(
            name: SalesChannelFileRenderParametersExtension::NAME,
            extension: new SalesChannelFileRenderParametersExtension($file, $context, $salesChannel),
            function: $this->buildDefaultParameters(...),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDefaultParameters(SalesChannelFile $file, SalesChannelContext $context, SalesChannelEntity $salesChannel): array
    {
        return [
            'context' => $context,
            'salesChannel' => $salesChannel,
            'salesChannelFile' => $file,
            'salesChannelFileContext' => $this->buildSalesChannelFileContext($salesChannel, $context),
        ];
    }

    private function loadSalesChannel(SalesChannelContext $context): SalesChannelEntity
    {
        $criteria = new Criteria([$context->getSalesChannelId()]);
        $criteria->setTitle('sales-channel-file-renderer::sales-channel');
        $criteria->addAssociation('languages.translationCode');
        $criteria->addAssociation('currencies');
        $criteria->addAssociation('domains');
        $criteria->getAssociation('languages')->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));
        $criteria->getAssociation('currencies')->addSorting(new FieldSorting('isoCode', FieldSorting::ASCENDING));
        $criteria->getAssociation('domains')->addSorting(new FieldSorting('url', FieldSorting::ASCENDING));

        $salesChannel = $this->salesChannelRepository->search($criteria, $context->getContext())->first();

        if (!$salesChannel instanceof SalesChannelEntity) {
            return $context->getSalesChannel();
        }

        return $salesChannel;
    }

    /**
     * @return array{baseUrl: string|null, publisher: string|null}
     */
    private function buildSalesChannelFileContext(SalesChannelEntity $salesChannel, SalesChannelContext $context): array
    {
        $baseUrl = $this->resolveBaseUrl($salesChannel, $context);
        $publisher = $baseUrl === null ? null : $this->extractPublisher($baseUrl);

        return [
            'baseUrl' => $baseUrl,
            'publisher' => $publisher,
        ];
    }

    private function resolveBaseUrl(SalesChannelEntity $salesChannel, SalesChannelContext $context): ?string
    {
        $domains = $salesChannel->getDomains();
        if ($domains === null || $domains->count() === 0) {
            return null;
        }

        $domainId = $context->getDomainId();
        if ($domainId !== null) {
            $domain = $domains->get($domainId);

            if ($domain instanceof SalesChannelDomainEntity) {
                return rtrim($domain->getUrl(), '/');
            }
        }

        $domain = $domains->first();

        return $domain instanceof SalesChannelDomainEntity ? rtrim($domain->getUrl(), '/') : null;
    }

    private function extractPublisher(string $baseUrl): ?string
    {
        $host = parse_url($baseUrl, \PHP_URL_HOST);

        return \is_string($host) && $host !== '' ? strtolower($host) : null;
    }
}
