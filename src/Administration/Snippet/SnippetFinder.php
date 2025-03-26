<?php declare(strict_types=1);

namespace Shopware\Administration\Snippet;

use Doctrine\DBAL\Connection;
use Shopware\Administration\Extension\SnippetExtension;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\HtmlSanitizer;

/**
 * @internal
 */
#[Package('discovery')]
class SnippetFinder implements SnippetFinderInterface
{
    public const ALLOWED_INTERSECTING_FIRST_LEVEL_SNIPPET_KEYS = [
        'sw-flow-custom-event',
    ];

    public function __construct(
        private readonly Connection $connection,
        private readonly SnippetFilesFinderInterface $filesFinder,
        private readonly ExtensionDispatcher $extensionDispatcher
    ) {
    }

    /**
     * @return array<string, string|mixed>
     */
    public function findSnippets(string $locale): array
    {
        $snippetFiles = $this->filesFinder->findSnippetFiles($locale);
        $snippets = $this->parseFiles($snippetFiles);

        $snippets = $this->extensionDispatcher
            ->publish(
                SnippetExtension::NAME,
                new SnippetExtension($snippets, $locale),
                fn ($snippets, $locale) => [...$snippets, ...$this->getAppAdministrationSnippets($locale, $snippets)]
            );

        if (!\count($snippets)) {
            return [];
        }

        return $snippets;
    }

    /**
     * @param array<int, string> $files
     *
     * @return array<string, mixed>
     */
    private function parseFiles(array $files): array
    {
        $snippets = [[]];

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content !== false) {
                $snippets[] = json_decode($content, true, 512, \JSON_THROW_ON_ERROR) ?? [];
            }
        }

        $snippets = array_replace_recursive(...$snippets);
        ksort($snippets);

        return $snippets;
    }

    /**
     * @param array<string, mixed> $existingSnippets
     *
     * @return array<string, mixed>
     */
    private function getAppAdministrationSnippets(string $locale, array $existingSnippets): array
    {
        $result = $this->connection->fetchAllAssociative(
            'SELECT app_administration_snippet.value
             FROM locale
             INNER JOIN app_administration_snippet ON locale.id = app_administration_snippet.locale_id
             INNER JOIN app ON app_administration_snippet.app_id = app.id
             WHERE locale.code = :code AND app.active = 1;',
            ['code' => $locale]
        );

        $decodedSnippets = array_map(
            fn ($data) => json_decode((string) $data['value'], true, 512, \JSON_THROW_ON_ERROR),
            $result
        );

        $appSnippets = array_replace_recursive([], ...$decodedSnippets);
        $appSnippets = $this->sanitizeAppSnippets($appSnippets);

        $this->validateAppSnippets($existingSnippets, $appSnippets);

        return $appSnippets;
    }

    /**
     * @param array<string, mixed> $existingSnippets
     * @param array<string, mixed> $appSnippets
     */
    private function validateAppSnippets(array $existingSnippets, array $appSnippets): void
    {
        $existingSnippetKeys = array_keys($existingSnippets);
        $appSnippetKeys = array_keys($appSnippets);
        $duplicatedKeys = $this->getInvalidIntersections($existingSnippetKeys, $appSnippetKeys);

        if (!empty($duplicatedKeys)) {
            throw SnippetException::duplicatedFirstLevelKey($duplicatedKeys);
        }
    }

    /**
     * @param array<string, mixed> $snippets
     *
     * @return array<string, mixed>
     */
    private function sanitizeAppSnippets(array $snippets): array
    {
        $sanitizer = new HtmlSanitizer();

        $sanitizedSnippets = [];
        foreach ($snippets as $key => $value) {
            if (\is_string($value)) {
                $sanitizedSnippets[$key] = $sanitizer->sanitize($value);

                continue;
            }

            if (\is_array($value)) {
                $sanitizedSnippets[$key] = $this->sanitizeAppSnippets($value);
            }
        }

        return $sanitizedSnippets;
    }

    /**
     * @param list<string> $snippetKeys
     * @param list<string> $additionalSnippetKeys
     *
     * @return list<string>
     */
    private function getInvalidIntersections(array $snippetKeys, array $additionalSnippetKeys): array
    {
        $intersections = array_intersect($snippetKeys, $additionalSnippetKeys);

        if (empty($intersections)) {
            return [];
        }

        return array_values(array_filter(
            $intersections,
            fn ($key) => !\in_array($key, self::ALLOWED_INTERSECTING_FIRST_LEVEL_SNIPPET_KEYS, true)
        ));
    }
}
