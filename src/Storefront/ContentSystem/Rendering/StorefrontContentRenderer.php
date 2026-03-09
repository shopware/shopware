<?php declare(strict_types=1);

namespace Shopware\Storefront\ContentSystem\Rendering;

use Shopware\Core\Framework\Log\Package;
use Twig\Environment;

#[Package('framework')]
class StorefrontContentRenderer
{
    /**
     * @internal
     */
    public function __construct(private readonly Environment $twig)
    {
    }

    /**
     * @param array<string, mixed>|null $fullPayload
     */
    public function renderLayout(?array $fullPayload): string
    {
        if ($fullPayload === null || !isset($fullPayload['elements']) || !\is_array($fullPayload['elements'])) {
            return '';
        }

        $html = '';

        foreach ($fullPayload['elements'] as $element) {
            if (!\is_array($element)) {
                continue;
            }

            $html .= $this->renderElement($element);
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $element
     */
    private function renderElement(array $element): string
    {
        $component = $element['component'] ?? null;
        if (!\is_string($component) || $component === '') {
            return '';
        }

        $slotHtml = [];
        if (isset($element['slots']) && \is_array($element['slots'])) {
            foreach ($element['slots'] as $slotName => $slotEntries) {
                if (!\is_string($slotName) || !\is_array($slotEntries)) {
                    continue;
                }

                $renderedChildren = [];
                foreach ($this->normalizeSlotEntries($slotEntries) as $childElement) {
                    if (!\is_array($childElement)) {
                        continue;
                    }

                    $renderedChildren[] = $this->renderElement($childElement);
                }

                $slotHtml[$slotName] = $renderedChildren;
            }
        }

        $template = $this->resolveTemplate($component);

        return $this->twig->render($template, [
            'element' => $element,
            'slotHtml' => $slotHtml,
            'componentName' => $component,
        ]);
    }

    /**
     * @param array<mixed> $slotEntries
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeSlotEntries(array $slotEntries): array
    {
        $normalized = [];

        foreach ($slotEntries as $slotKey => $slotEntry) {
            if ($slotKey === 'apiAlias') {
                continue;
            }

            if (\is_array($slotEntry)) {
                $normalized[] = $slotEntry;
            }
        }

        return $normalized;
    }

    private function resolveTemplate(string $component): string
    {
        $slug = mb_strtolower($component);
        $slug = str_replace(':', '-', $slug);
        $slug = preg_replace('/[^a-z0-9\-]+/', '-', $slug) ?? 'sw-unknown';
        $slug = trim($slug, '-');

        $template = '@Storefront/storefront/content-system/component/' . $slug . '.html.twig';

        if ($this->twig->getLoader()->exists($template)) {
            return $template;
        }

        return '@Storefront/storefront/content-system/component/sw-unknown.html.twig';
    }
}
