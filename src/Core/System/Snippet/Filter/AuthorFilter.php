<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Filter;

use Shopware\Core\Framework\Log\Package;

#[Package('system-settings')]
class AuthorFilter extends AbstractFilter implements SnippetFilterInterface
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'author';
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
        foreach ($snippets as $setId => $set) {
            foreach ($set['snippets'] as $translationKey => $snippet) {
                if (!\in_array($snippet['author'], $requestFilterValue, true)) {
                    continue;
                }
                $result[$setId]['snippets'][$translationKey] = $snippet;
            }
        }

        return $this->readjust($result, $snippets);
    }
}
