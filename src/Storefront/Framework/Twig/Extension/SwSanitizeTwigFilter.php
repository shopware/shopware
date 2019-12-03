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

    public function getFilters(): array
    {
        return [
            new TwigFilter('sw_sanitize', [$this, 'sanitize'], ['is_safe' => ['html']]),
        ];
    }

    public function sanitize(string $text, $options = null, bool $override = false): string
    {
        if ($options === null) {
            $options = [];
        }
        $hash = md5(json_encode($options)) . $override;

        if (!isset($this->purifiers[$hash])) {
            $config = $this->getConfig($options, $override);
            $this->purifiers[$hash] = new \HTMLPurifier($config);
        }

        return $this->purifiers[$hash]->purify($text);
    }

    private function getConfig($options, bool $override): \HTMLPurifier_Config
    {
        $config = \HTMLPurifier_Config::createDefault();

        if ($override && empty($options)) {
            $config->set('HTML.AllowedElements', []);
            $config->set('HTML.AllowedAttributes', []);

            return $config;
        } elseif ($override) {
            $this->allowedElements = [];
            $this->allowedAttributes = [];
        }

        if ($options === null) {
            $config->set('HTML.AllowedElements', $this->allowedElements);
            $config->set('HTML.AllowedAttributes', $this->allowedAttributes);

            return $config;
        }

        $this->setConfigArrays($options);

        $config->set('HTML.AllowedElements', $this->allowedElements);
        $config->set('HTML.AllowedAttributes', $this->allowedAttributes);

        return $config;
    }

    private function setConfigArrays(array $options): void
    {
        foreach ($options as $element => $attributes) {
            if ($element !== '*') {
                array_push($this->allowedElements, $element);
            }
            foreach ($attributes as $attribute) {
                array_push($this->allowedAttributes, ($element . '.' . $attribute));
            }
        }
    }
}
