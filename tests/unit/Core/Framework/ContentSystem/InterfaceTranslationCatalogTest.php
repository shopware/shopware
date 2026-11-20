<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

/**
 * @internal
 */
#[Package('framework')]
#[CoversNothing]
class InterfaceTranslationCatalogTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $usedKeys = [];

    public function testShippedInterfaceTextHasMatchingSnippets(): void
    {
        $root = \dirname(__DIR__, 5);
        $snippetDirectory = $root . '/src/Administration/Resources/app/administration/src/module/sw-experience-studio/snippet/';
        $english = json_decode((string) file_get_contents($snippetDirectory . 'en.json'), true, 512, \JSON_THROW_ON_ERROR);
        $german = json_decode((string) file_get_contents($snippetDirectory . 'de.json'), true, 512, \JSON_THROW_ON_ERROR);
        $base = $root . '/src/Core/Framework/ContentSystem/Layout/';
        $directories = [
            'elements' => 'Type/Definitions',
            'presets' => 'Preset/Definitions',
            'styles' => 'Element/Style/Definitions',
        ];
        $count = 0;

        foreach ($directories as $kind => $directory) {
            $files = (new Finder())->files()->name('*.yaml')->in($base . $directory);
            foreach ($files as $file) {
                $data = Yaml::parseFile($file->getPathname(), Yaml::PARSE_CUSTOM_TAGS);
                if ($kind === 'elements') {
                    $this->assertText($data['meta'], 'label', $english, $german);
                    $this->assertProperties($data['properties'] ?? [], $english, $german);
                } elseif ($kind === 'presets') {
                    $this->assertText($data, 'name', $english, $german);
                    $this->assertText($data, 'description', $english, $german);
                } else {
                    $this->assertAdminUi($data['adminUI'] ?? [], $english, $german);
                }
                ++$count;
            }
        }

        static::assertGreaterThan(0, $count);
        $used = array_values(array_unique($this->usedKeys));
        $englishKeys = $this->catalogKeys($english['sw-experience-studio']);
        $germanKeys = $this->catalogKeys($german['sw-experience-studio']);
        sort($used);
        sort($englishKeys);
        sort($germanKeys);
        static::assertSame($used, $englishKeys, 'English snippet references contain missing or stale keys.');
        static::assertSame($used, $germanKeys, 'German snippet references contain missing or stale keys.');
    }

    /**
     * @param array<string, mixed> $snippets
     *
     * @return list<string>
     */
    private function catalogKeys(array $snippets): array
    {
        $keys = [];
        foreach (['elements', 'presets', 'styles'] as $kind) {
            foreach ($snippets[$kind] ?? [] as $name => $entries) {
                // Panel headings predate the content metadata catalog and use the same namespace.
                if ($kind === 'elements') {
                    unset($entries['panels']);
                }

                array_push($keys, ...$this->snippetKeys($entries, 'sw-experience-studio.' . $kind . '.' . $name));
            }
        }

        return $keys;
    }

    /**
     * @param array<string, mixed> $snippets
     *
     * @return list<string>
     */
    private function snippetKeys(array $snippets, string $prefix): array
    {
        $keys = [];
        foreach ($snippets as $part => $value) {
            $key = $prefix . '.' . $part;
            if (\is_array($value)) {
                array_push($keys, ...$this->snippetKeys($value, $key));
            } else {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * @param array<string, mixed> $properties
     * @param array<string, mixed> $english
     * @param array<string, mixed> $german
     */
    private function assertProperties(array $properties, array $english, array $german): void
    {
        foreach ($properties as $property) {
            if (($property['adminUI']['component'] ?? null) === 'select' && isset($property['enum'])) {
                $options = $property['adminUI']['props']['options'] ?? [];
                static::assertEqualsCanonicalizing(array_values($property['enum']), array_column($options, 'value'), $property['title']);
                foreach ($options as $option) {
                    static::assertArrayHasKey('label', $option, $property['title']);
                }
            }

            $this->assertText($property, 'title', $english, $german);
            $this->assertAdminUi($property['adminUI'] ?? [], $english, $german);
            $this->assertProperties($property['properties'] ?? [], $english, $german);
        }
    }

    /**
     * @param array<string, mixed> $adminUi
     * @param array<string, mixed> $english
     * @param array<string, mixed> $german
     */
    private function assertAdminUi(array $adminUi, array $english, array $german): void
    {
        foreach (['label', 'description', 'helpText'] as $field) {
            if (isset($adminUi[$field])) {
                $this->assertText($adminUi, $field, $english, $german);
            }
        }
        foreach ($adminUi['props']['options'] ?? [] as $option) {
            foreach (['label', 'description'] as $field) {
                if (isset($option[$field])) {
                    $this->assertText($option, $field, $english, $german);
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $english
     * @param array<string, mixed> $german
     */
    private function assertText(array $metadata, string $field, array $english, array $german): void
    {
        static::assertArrayHasKey($field . 'Snippet', $metadata, 'Missing snippet reference for ' . $metadata[$field]);
        $key = $metadata[$field . 'Snippet'];
        static::assertIsString($key);
        $this->usedKeys[] = $key;
        $en = $english;
        $de = $german;
        foreach (explode('.', $key) as $part) {
            static::assertIsArray($en, $key);
            static::assertIsArray($de, $key);
            static::assertArrayHasKey($part, $en, $key);
            static::assertArrayHasKey($part, $de, $key);
            $en = $en[$part];
            $de = $de[$part];
        }
        static::assertSame($metadata[$field], $en, $key);
        static::assertIsString($de, $key);
        static::assertNotSame('', $de, $key);
    }
}
