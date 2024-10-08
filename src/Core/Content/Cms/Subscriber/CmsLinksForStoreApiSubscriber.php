<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Subscriber;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\CmsPageCollection;
use Shopware\Core\Content\Cms\Events\CmsPageLoadedEvent;
use Shopware\Core\Content\Media\MediaUrlPlaceholderHandlerInterface;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService as ApiDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('buyers-experience')]
class CmsLinksForStoreApiSubscriber implements EventSubscriberInterface
{
    private string $delimiter = '###UNIQUE_DELIMITER###';

    /**
     * @internal
     */
    public function __construct(
        private readonly SeoUrlPlaceholderHandlerInterface $seoUrlReplacer,
        private readonly MediaUrlPlaceholderHandlerInterface $mediaUrlReplacer,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CmsPageLoadedEvent::class => 'relativeLinksForStoreAPIOutput',
        ];
    }

    public function relativeLinksForStoreAPIOutput(
        CmsPageLoadedEvent $event
    ): void {
        $request = $event->getRequest();
        if ($this->isStoreApiRequest($request)) {
            $pages = $event->getResult();
            $this->processCmsPageCollection($pages, $request->getSchemeAndHttpHost(), $event->getSalesChannelContext());
        }
    }

    private function isStoreApiRequest(Request $request): bool
    {
        $routeScope = $request->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE);
        if (\is_array($routeScope)) {
            foreach ($routeScope as $scope) {
                if ($scope === ApiDefinition::STORE_API) {
                    return true;
                }
            }
        }

        return false;
    }

    private function processCmsPageCollection(
        CmsPageCollection $pages,
        string $schemeAndHttpHost,
        SalesChannelContext $salesChannelContext
    ): void {
        // Step 1: Collect all static string content
        [$contentMap, $concatenatedContent] = $this->collectStaticContent($pages);

        // Step 2 & 3: Process the big string with replaceLinks and split it back into chunks
        $transformedChunks = $this->processAndSplitContent($concatenatedContent, $schemeAndHttpHost, $salesChannelContext);

        // Step 4: Replace the content with the transformed chunks (data property stays unchanged)
        $this->replaceContent($pages, $transformedChunks, $contentMap);
    }

    /**
     * @return array{array<string, array<string, string>>, string}
     */
    private function collectStaticContent(CmsPageCollection $pages): array
    {
        $contentMap = [];
        $concatenatedContent = '';

        foreach ($pages as $page) {
            $elements = array_merge(
                $page->getElementsOfType('text'),
                $page->getElementsOfType('html')
            );

            foreach ($elements as $slot) {
                $fields = [
                    'translatedConfig' => $slot->getTranslated()['config']['content']['value'] ?? null,
                    'config' => $slot->getConfig()['content']['value'] ?? null,
                ];
                foreach (array_filter($fields) as $field => $content) {
                    $contentMap[$slot->getId()][$field] = $content;
                    $concatenatedContent .= $content . $this->delimiter;
                }
            }
        }

        return [$contentMap, $concatenatedContent];
    }

    /**
     * @return array<int, string>
     */
    private function processAndSplitContent(
        string $concatenatedContent,
        string $schemeAndHttpHost,
        SalesChannelContext $salesChannelContext
    ): array {
        $concatenatedContentReplaced = $this->replaceLinks($concatenatedContent, $schemeAndHttpHost, $salesChannelContext);
        if ($this->delimiter === '') {
            return [];
        }

        return array_filter(explode($this->delimiter, $concatenatedContentReplaced));
    }

    private function replaceLinks(string $content, string $schemeAndHttpHost, SalesChannelContext $salesChannelContext): string
    {
        $content = $this->mediaUrlReplacer->replace($content);
        $content = $this->seoUrlReplacer->replace($content, '', $salesChannelContext);

        return (string) $this->removeSchemeAndHttpHostFromLinks($content, $schemeAndHttpHost);
    }

    private function removeSchemeAndHttpHostFromLinks(string $content, string $schemeAndHttpHost = ''): ?string
    {
        return preg_replace_callback(
            '/<a.+?\s*href\s*=\s*["\']?([^"\'\s>]+)["\']?/',
            function (array $matches) use ($schemeAndHttpHost) {
                return str_replace($schemeAndHttpHost, '', $matches[0]);
            },
            $content
        );
    }

    /**
     * @param array<int, string> $transformedChunks
     * @param array<string, array<string, string>> $contentMap
     */
    private function replaceContent(
        CmsPageCollection $pages,
        array $transformedChunks,
        array $contentMap
    ): void {
        foreach ($pages as $page) {
            foreach ($page->getAllElements() as $slot) {
                if (!isset($contentMap[$slot->getId()])) {
                    continue;
                }

                foreach ($contentMap[$slot->getId()] as $field => $originalContent) {
                    $transformedChunk = array_shift($transformedChunks);
                    if ($transformedChunk === null) {
                        continue;
                    }

                    $this->replaceSlotContent($slot, $field, $transformedChunk);
                }
            }
        }
    }

    private function replaceSlotContent(CmsSlotEntity $slot, string $field, string $transformedChunk): void
    {
        switch ($field) {
            case 'translatedConfig':
                $translated = $slot->getTranslated();
                $translated['config']['content']['value'] = $transformedChunk;
                $slot->setTranslated($translated);
                break;
            case 'config':
                $config = $slot->getConfig();
                $config['content']['value'] = $transformedChunk;
                $slot->setConfig($config);
                break;
        }
    }
}
