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
        private readonly array $fieldSets = [],
        private readonly bool $enabled = true
    ) {
        $this->cacheDir = (string) $cacheDir;
    }

    /**
     * @param array<string, array<string>>|null $options
     */
    public function sanitize(string $text, ?array $options = [], bool $override = false, ?string $field = null): string
    {
        if (!$this->enabled) {
            return $text;
        }

        $options ??= [];

        $hash = md5(\sprintf('%s%s', json_encode($options, \JSON_THROW_ON_ERROR), $field));

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

        $this->addHTML5Tags($definition);

        foreach ($customAttributes as $tag => $attributes) {
            foreach ($attributes as $attribute) {
                $definition->addAttribute($tag, $attribute, 'Text');
            }
        }

        return $config;
    }

    private function addHTML5Tags(\HTMLPurifier_HTMLDefinition $definition): \HTMLPurifier_HTMLDefinition
    {
        $definition->addElement('section', 'Block', 'Flow', 'Common');
        $definition->addElement('nav', 'Block', 'Flow', 'Common');
        $definition->addElement('article', 'Block', 'Flow', 'Common');
        $definition->addElement('aside', 'Block', 'Flow', 'Common');
        $definition->addElement('header', 'Block', 'Flow', 'Common');
        $definition->addElement('footer', 'Block', 'Flow', 'Common');
        $definition->addElement('canvas', 'Block', 'Flow', 'Common', [
            'width' => 'Length',
            'height' => 'Length',
        ]);
        $definition->addElement('bdi', 'Block', 'Flow', 'Common');
        $definition->addElement('audio', 'Block', 'Flow', 'Common', [
            'src' => 'URI',
            'preload' => 'Enum#auto,metadata,none',
            'autoplay' => new \HTMLPurifier_AttrDef_HTML_Bool(true),
            'loop' => new \HTMLPurifier_AttrDef_HTML_Bool(true),
            'muted' => new \HTMLPurifier_AttrDef_HTML_Bool(true),
            'controls' => new \HTMLPurifier_AttrDef_HTML_Bool(true),
        ]);
        $definition->addElement('datalist', 'Block', 'Flow', 'Common', [
            'id' => 'ID',
        ]);
        $definition->addElement('dialog', 'Block', 'Flow', 'Common', [
            'open' => new \HTMLPurifier_AttrDef_HTML_Bool(true),
        ]);
        $definition->addElement('embed', 'Block', 'Flow', 'Common', [
            'src' => 'URI',
            'type' => 'Text',
            'width' => 'Length',
            'height' => 'Length',
        ]);
        $definition->addElement('main', 'Block', 'Flow', 'Common');
        $definition->addElement('menu', 'Block', 'Flow', 'Common');
        $definition->addElement('meter', 'Block', 'Flow', 'Common', [
            'form' => 'ID',
            'value' => 'Text',
            'min' => 'Length',
            'max' => 'Length',
            'low' => 'Text',
            'high' => 'Text',
            'optimum' => 'Text',
        ]);
        $definition->addElement('progress', 'Block', 'Flow', 'Common', [
            'value' => 'Number',
            'max' => 'Number',
        ]);
        $definition->addElement('rp', 'Block', 'Flow', 'Common');
        $definition->addElement('rt', 'Block', 'Flow', 'Common');
        $definition->addElement('ruby', 'Block', 'Flow', 'Common');
        $definition->addElement('summary', 'Block', 'Flow', 'Common');
        $definition->addElement('time', 'Block', 'Flow', 'Common', [
            'datetime' => 'Text',
        ]);
        $definition->addElement('output', 'Block', 'Flow', 'Common', [
            'for' => 'ID',
            'form' => 'ID',
            'name' => 'CDATA',
        ]);
        $definition->addElement('svg', 'Block', 'Flow', 'Common', [
            'width' => 'Length',
            'height' => 'Length',
        ]);
        $definition->addElement('track', 'Block', 'Flow', 'Common', [
            'default' => new \HTMLPurifier_AttrDef_HTML_Bool(true),
            'kind' => 'Enum#subtitles,captions,descriptions,chapters,metadata',
            'label' => 'Text',
            'src' => 'URI',
            'srclang' => 'LanguageCode',
        ]);
        $definition->addElement(
            'details',
            'Block',
            'Flow',
            'Common',
            [
                'open' => new \HTMLPurifier_AttrDef_HTML_Bool(true),
            ]
        );

        $definition->addElement('figure', 'Block', 'Optional: (figcaption, Flow) | (Flow, figcaption) | Flow', 'Common');
        $definition->addElement('figcaption', 'Inline', 'Flow', 'Common');
        $definition->addElement('video', 'Block', 'Optional: (source, Flow) | (Flow, source) | Flow', 'Common', [
            'src' => 'URI',
            'width' => 'Length',
            'height' => 'Length',
            'poster' => 'URI',
            'preload' => 'Enum#auto,metadata,none',
            'controls' => new \HTMLPurifier_AttrDef_HTML_Bool(true),
            'autoplay' => new \HTMLPurifier_AttrDef_HTML_Bool(true),
            'loop' => new \HTMLPurifier_AttrDef_HTML_Bool(true),
            'muted' => new \HTMLPurifier_AttrDef_HTML_Bool(true),
        ]);
        $definition->addElement('source', 'Block', 'Flow', 'Common', [
            'src' => 'URI',
            'type' => 'Text',
            'media' => 'Text',
            'sizes' => 'Text',
            'srcset' => 'Text',
            'crossorigin' => 'Enum#anonymous,use-credentials',
        ]);

        $definition->addElement('mark', 'Inline', 'Inline', 'Common');
        $definition->addElement('wbr', 'Inline', 'Empty', 'Core');

        // Add new HTML5 input types
        $definition->addElement(
            'input',
            'Form',
            'Empty',
            'Common',
            [
                'accept' => 'Text',
                'alt' => 'Text',
                'autocomplete' => 'Enum#on,off',
                'autofocus' => new \HTMLPurifier_AttrDef_HTML_Bool(true),
                'checked' => new \HTMLPurifier_AttrDef_HTML_Bool(true),
                'dirname' => 'Text',
                'disabled' => new \HTMLPurifier_AttrDef_HTML_Bool(true),
                'form' => 'ID',
                'formaction' => 'URI',
                'formenctype' => 'Enum#application/x-www-form-urlencoded,multipart/form-data,text/plain',
                'formmethod' => 'Enum#get,post',
                'formnovalidate' => new \HTMLPurifier_AttrDef_HTML_Bool(true),
                'formtarget' => 'Enum#_blank,_self,_parent,_top',
                'height' => 'Length',
                'list' => 'ID',
                'max' => 'Text',
                'maxlength' => 'Number',
                'min' => 'Text',
                'minlength' => 'Number',
                'multiple' => new \HTMLPurifier_AttrDef_HTML_Bool(true),
                'name' => 'CDATA',
                'pattern' => 'Text',
                'placeholder' => 'Text',
                'readonly' => new \HTMLPurifier_AttrDef_HTML_Bool(true),
                'required' => new \HTMLPurifier_AttrDef_HTML_Bool(true),
                'size' => 'Number',
                'src' => 'URI',
                'step' => 'Text',
                'type' => 'Enum#text,password,checkbox,radio,submit,reset,file,hidden,image,button,date,time,datetime-local,week,month,number,email,url,search,tel,color,range',
                'value' => 'Text',
                'width' => 'Length',
            ]
        );

        return $definition;
    }
}
