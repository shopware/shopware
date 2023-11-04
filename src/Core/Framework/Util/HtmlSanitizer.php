<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\Util;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class HtmlSanitizer
{
    /**
     * @var \HTMLPurifier[]
     */
    private array $purifiers = [];

    private readonly string $cacheDir;

    /**
     * @var array<string, string>
     */
    private array $cache = [];

    /**
     * @internal
     *
     * @param array<string, array{name?: string, tags?: list<string>, attributes?: list<string>, options?: array<string, mixed>, custom_attributes?: array<string, array<string, list<string>>>}> $sets
     * @param array<string, array{sets?: list<string>|null}> $fieldSets
     */
    public function __construct(
        ?string $cacheDir = null,
        private readonly bool $cacheEnabled = true,
        private array $sets = [],
        private readonly array $fieldSets = []
    ) {
        $this->cacheDir = (string) $cacheDir;
    }

    /**
     * @param array<string, array<string>>|null $options
     */
    public function sanitize(string $text, ?array $options = [], bool $override = false, ?string $field = null): string
    {
        $options ??= [];

        $hash = md5(sprintf('%s%s', json_encode($options, \JSON_THROW_ON_ERROR), $field));

        if ($override) {
            $hash .= '-override';
        }

        $textKey = $hash . md5($text);
        if (isset($this->cache[$textKey])) {
            return $this->cache[$textKey];
        }

        if (!isset($this->purifiers[$hash])) {
            $config = $this->getConfig($options, $override, $field);
            $this->purifiers[$hash] = new \HTMLPurifier($config);
        }

        $this->cache[$textKey] = $this->purifiers[$hash]->purify($text);

        return $this->cache[$textKey];
    }

    private function getBaseConfig(): \HTMLPurifier_Config
    {
        $config = \HTMLPurifier_Config::createDefault();

        if ($this->cacheDir !== '') {
            $config->set('Cache.SerializerPath', $this->cacheDir);
        }

        if (!$this->cacheEnabled) {
            $config->set('Cache.DefinitionImpl', null);
        }

        $config->set('Cache.SerializerPermissions', 0775 & ~umask());

        return $config;
    }

    /**
     * @param array<string, array<string>> $options
     */
    private function getConfig(array $options, bool $override, ?string $field): \HTMLPurifier_Config
    {
        $config = $this->getBaseConfig();

        $allowedElements = [];
        $allowedAttributes = [];
        $customAttributes = [];

        foreach ($options as $element => $attributes) {
            if ($element !== '*') {
                $allowedElements[] = $element;
            }

            foreach ($attributes as $attr) {
                $allowedAttributes[] = $element === '*' ? $attr : "{$element}.{$attr}";
            }
        }

        if (!$override) {
            $sets = $this->fieldSets[$field]['sets'] ?? ['basic'];

            foreach ($sets as $set) {
                if (isset($this->sets[$set]['tags'])) {
                    $allowedElements = array_merge($allowedElements, $this->sets[$set]['tags']);
                }
                if (isset($this->sets[$set]['attributes'])) {
                    $allowedAttributes = array_merge($allowedAttributes, $this->sets[$set]['attributes']);
                }
                if (isset($this->sets[$set]['custom_attributes'])) {
                    foreach ($this->sets[$set]['custom_attributes'] as $customAttribute) {
                        foreach ($customAttribute['tags'] as $tag) {
                            $customAttributes[$tag] = array_merge($customAttribute['attributes'], $customAttributes[$tag] ?? []);
                        }
                    }
                }
                if (isset($this->sets[$set]['options'])) {
                    foreach ($this->sets[$set]['options'] as $key => $value) {
                        if (\is_array($value) && \array_key_exists('values', $value)) {
                            $value = $value['values'];
                        }

                        $config->set($key, $value);
                    }
                }
            }
        }

        $config->set('HTML.AllowedElements', $allowedElements);
        $config->set('HTML.AllowedAttributes', $allowedAttributes);

        $definition = $config->getHTMLDefinition(true);

        if ($definition === null) {
            return $config;
        }

        foreach ($customAttributes as $tag => $attributes) {
            foreach ($attributes as $attribute) {
                $definition->addAttribute($tag, $attribute, 'Text');
            }
        }

        return $config;
    }
}
