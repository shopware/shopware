<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Snippet\Services;

class SnippetFlattener implements SnippetFlattenerInterface
{
    /**
     * {@inheritdoc}
     */
    public function flatten(array $array, string $prefix = ''): array
    {
        $result = [];
        foreach ($array as $index => $value) {
            $newIndex = $prefix . (empty($prefix) ? '' : '.') . $index;

            if (\is_array($value)) {
                $result = array_merge($result, $this->flatten($value, $newIndex));
            } else {
                $result[$newIndex] = $value;
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function unflatten(array $snippets): array
    {
        $output = [];
        foreach ($snippets as $snippet) {
            $current = &$output;
            foreach (explode('.', $snippet->getTranslationKey()) as $key) {
                $current = &$current[$key];
            }

            $current = $snippet->getValue();
        }

        return $output;
    }
}
