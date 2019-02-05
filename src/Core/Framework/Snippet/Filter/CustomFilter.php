<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Snippet\Filter;

class CustomFilter extends AbstractFilter implements SnippetFilterInterface
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'custom';
    }

    /**
     * {@inheritdoc}
     */
    public function filter(array $snippets, $requestFilterValue, array $additionalData = []): array
    {
        if (empty($requestFilterValue) || !is_bool($requestFilterValue)) {
            return $snippets;
        }

        $authors = $additionalData['customAuthors'];
        $result = [];
        foreach ($snippets as $setId => $set) {
            foreach ($set['snippets'] as $translationKey => $snippet) {
                if (!in_array($snippet['author'], $authors)) {
                    continue;
                }
                $result[$setId]['snippets'][$translationKey] = $snippet;
            }
        }

        return $this->readjust($result, $snippets);
    }
}
