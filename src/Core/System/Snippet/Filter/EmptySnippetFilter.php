<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Filter;

use Shopware\Core\Framework\Log\Package;

#[Package('system-settings')]
class EmptySnippetFilter extends AbstractFilter implements SnippetFilterInterface
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'empty';
    }

    /**
     * {@inheritdoc}
     */
    public function filter(array $snippets, $requestFilterValue): array
    {
        $result = [];
        foreach ($snippets as $setId => $set) {
            foreach ($set['snippets'] as $translationKey => $snippet) {
                if (!empty($snippet['value'])) {
                    continue;
                }

                $result[$setId]['snippets'][$translationKey] = $snippet;
            }
        }

        return $this->readjust($result, $snippets);
    }
}
