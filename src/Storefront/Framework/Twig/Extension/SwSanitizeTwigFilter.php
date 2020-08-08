<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig\Extension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class SwSanitizeTwigFilter extends AbstractExtension
{
    private $allowedElements = [
        'a',
        'abbr',
        'acronym',
        'address',
        'b',
        'bdo',
        'big',
        'blockquote',
        'br',
        'caption',
        'center',
        'cite',
        'code',
        'col',
        'colgroup',
        'dd',
        'del',
        'dfn',
        'dir',
        'div',
        'dl',
        'dt',
        'em',
        'font',
        'h1',
        'h2',
        'h3',
        'h4',
        'h5',
        'h6',
        'hr',
        'i',
        'ins',
        'kbd',
        'li',
        'menu',
        'ol',
        'p',
        'pre',
        'q',
        's',
        'samp',
        'small',
        'span',
        'strike',
        'strong',
        'sub',
        'sup',
        'table',
        'tbody',
        'td',
        'tfoot',
        'th',
        'thead',
        'tr',
        'tt',
        'u',
        'ul',
        'var',
    ];

    private $allowedAttributes = [
        'align',
        'bgcolor',
        'border',
        'cellpadding',
        'cellspacing',
        'cite',
        'class',
        'clear',
        'color',
        'colspan',
        'dir',
        'face',
        'frame',
        'height',
        'href',
        'id',
        'lang',
        'name',
        'noshade',
        'nowrap',
        'rel',
        'rev',
        'rowspan',
        'scope',
        'size',
        'span',
        'start',
        'style',
        'summary',
        'title',
        'type',
        'valign',
        'value',
        'width',
    ];

    /**
     * @var \HTMLPurifier[]
     */
    private $purifiers = [];

    /**
     * @var string
     */
    private $cacheDir;

    /**
     * @var bool
     */
    private $cacheEnabled;

    public function __construct(?string $cacheDir = null, bool $cacheEnabled = true)
    {
        $this->cacheDir = $cacheDir;
        $this->cacheEnabled = $cacheEnabled;
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('sw_sanitize', [$this, 'sanitize'], ['is_safe' => ['html']]),
        ];
    }

    public function sanitize(string $text, ?array $options = [], bool $override = false): string
    {
        $options = $options ?? [];

        $hash = md5(json_encode($options));

        if ($override) {
            $hash .= '-override';
        }

        if (!isset($this->purifiers[$hash])) {
            $config = $this->getConfig($options, $override);
            $this->purifiers[$hash] = new \HTMLPurifier($config);
        }

        return $this->purifiers[$hash]->purify($text);
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

    private function getConfig(array $options, bool $override): \HTMLPurifier_Config
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
            $allowedElements = array_merge($this->allowedElements, $allowedElements);
            $allowedAttributes = array_merge($this->allowedAttributes, $allowedAttributes);
        }

        $config->set('HTML.AllowedElements', $allowedElements);
        $config->set('HTML.AllowedAttributes', $allowedAttributes);

        return $config;
    }
}
