<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Filter;

class EditedFilter extends AbstractFilter implements SnippetFilterInterface
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'edited';
    }

    /**
     * {@inheritdoc}
     */
    public function filter(array $snippets, $requestFilterValue): array
    {
        $result = [];
        foreach ($snippets as $setId => $set) {
            foreach ($set['snippets'] as $translationKey => $snippet) {
                if ($snippet['id'] === null || mb_strpos($snippet['author'], 'user/') === 0) {
                    continue;
                }

                $result[$setId]['snippets'][$translationKey] = $snippet;
            }
        }

        return $this->readjust($result, $snippets);
    }
}
