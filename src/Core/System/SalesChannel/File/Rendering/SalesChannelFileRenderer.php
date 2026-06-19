<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\File\Rendering;

use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\File\Discovery\SalesChannelFile;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Twig\Environment;

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
        private readonly SalesChannelFileTemplateOverrideLoader $templateOverrideLoader,
        private readonly SeoUrlPlaceholderHandlerInterface $seoUrlPlaceholderHandler,
        private readonly EntityRepository $salesChannelRepository,
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
            $templateName = \sprintf(self::USER_PROVIDED_CONTENT_TEMPLATE, $file->templatePath);
            $overrideTemplates[$templateName] = $this->buildUserProvidedContentTemplate($file, $userProvidedContent, $this->getRenderTemplateName($file));
        }

        $content = $this->templateOverrideLoader->withTemplateOverrides(
            $overrideTemplates,
            fn (): string => $this->twig->render($templateName, $parameters)
        );

        return $this->seoUrlPlaceholderHandler->replace($content, '', $context);
    }

    private function getRenderTemplateName(SalesChannelFile $file): string
    {
        $key = array_key_last($file->templates);
        $templateName = $key === null ? null : $file->templates[$key];

        return \is_string($templateName) ? $templateName : $file->baseTemplateName;
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

    private function buildUserProvidedContentTemplate(SalesChannelFile $file, string $userProvidedContent, string $parentTemplateName): string
    {
        $encodedContent = json_encode($userProvidedContent, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        \assert(\is_string($encodedContent));

        // The parent was already resolved by discovery; using sw_extends here would resolve
        // the hierarchy again from the generated namespace and could skip extension templates.
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
        return [
            'context' => $context,
            'salesChannel' => $this->loadSalesChannel($context),
            'salesChannelFile' => $file,
        ];
    }

    private function loadSalesChannel(SalesChannelContext $context): SalesChannelEntity
    {
        $criteria = new Criteria([$context->getSalesChannelId()]);
        $criteria->setTitle('sales-channel-file-renderer::sales-channel');
        $criteria->addAssociation('languages.translationCode');
        $criteria->addAssociation('currencies');
        $criteria->getAssociation('languages')->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));
        $criteria->getAssociation('currencies')->addSorting(new FieldSorting('isoCode', FieldSorting::ASCENDING));

        $salesChannel = $this->salesChannelRepository->search($criteria, $context->getContext())->first();

        if (!$salesChannel instanceof SalesChannelEntity) {
            return $context->getSalesChannel();
        }

        return $salesChannel;
    }
}
