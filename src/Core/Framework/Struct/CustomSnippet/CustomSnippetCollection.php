<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Struct\CustomSnippet;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<CustomSnippet>
 */
final class CustomSnippetCollection extends Collection
{
    public function getExpectedClass(): string
    {
        return CustomSnippet::class;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function toArray(): array
    {
        $data = [];
        foreach ($this->getElements() as $snippet) {
            $data[] = [
                'type' => $snippet->getType(),
                'value' => $snippet->getValue(),
            ];
        }

        return $data;
    }

    public function getApiAlias(): string
    {
        return 'custom_snippet_collection';
    }
}
