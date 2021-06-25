<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\Util;

class HtmlSanitizer
{
    /**
     * @var \HTMLPurifier[]
     */
    private array $purifiers = [];

    private string $cacheDir;

    private bool $cacheEnabled;

    private array $sets;

    private array $fieldSets;

    private array $cache = [];

    public function __construct(
        ?string $cacheDir = null,
        bool $cacheEnabled = true,
        array $sets = [],
        array $fieldSets = []
    ) {
        $this->cacheDir = (string) $cacheDir;
        $this->cacheEnabled = $cacheEnabled;
        $this->sets = $sets;
        $this->fieldSets = $fieldSets;
    }

    public function sanitize(string $text, ?array $options = [], bool $override = false, ?string $field = null): string
    {
        $options = $options ?? [];

        $hash = md5(sprintf('%s%s', (string) json_encode($options), (string) $field));

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

        return $config;
    }

    private function getConfig(array $options, bool $override, ?string $field): \HTMLPurifier_Config
    {
        $config = $this->getBaseConfig();

        $allowedElements = [];
        $allowedAttributes = [];

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
                if (isset($this->sets[$set]['options'])) {
                    foreach ($this->sets[$set]['options'] as $key => $value) {
                        $config->set($key, $value);
                    }
                }
            }
        }

        $config->set('HTML.AllowedElements', $allowedElements);
        $config->set('HTML.AllowedAttributes', $allowedAttributes);

        return $config;
    }
}
