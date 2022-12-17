<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Filter;

/**
 * @package system-settings
 */
class TermFilter extends AbstractFilter implements SnippetFilterInterface
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'term';
    }

    /**
     * {@inheritdoc}
     */
    public function filter(array $snippets, $requestFilterValue): array
    {
        if (empty($requestFilterValue) || !\is_string($requestFilterValue)) {
            return $snippets;
        }

        $result = [];
        foreach ($snippets as $setId => $set) {
            foreach ($set['snippets'] as $translationKey => $snippet) {
                $term = sprintf('/%s/i', $requestFilterValue);
                $keyMatch = preg_match($term, $snippet['translationKey']);
                $valueMatch = preg_match($term, $snippet['value']);

                if ($keyMatch === 0 && $valueMatch === 0) {
                    continue;
                }
                $result[$setId]['snippets'][$translationKey] = $snippet;
            }
        }

        return $this->readjust($result, $snippets);
    }
}
