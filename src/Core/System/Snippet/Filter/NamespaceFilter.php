<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Filter;

use Shopware\Core\Framework\Log\Package;

#[Package('system-settings')]
class NamespaceFilter extends AbstractFilter implements SnippetFilterInterface
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'namespace';
    }

    /**
     * {@inheritdoc}
     */
    public function filter(array $snippets, $requestFilterValue): array
    {
        if (empty($requestFilterValue) || !\is_array($requestFilterValue)) {
            return $snippets;
        }

        $result = [];
        foreach ($requestFilterValue as $term) {
            foreach ($snippets as $setId => $set) {
                foreach ($set['snippets'] as $translationKey => $snippet) {
                    if (!fnmatch(sprintf('%s*', (string) $term), $snippet['translationKey'], \FNM_CASEFOLD)) {
                        continue;
                    }
                    $result[$setId]['snippets'][$translationKey] = $snippet;
                }
            }
        }

        return $this->readjust($result, $snippets);
    }
}
