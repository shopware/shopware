<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Cms;

use Shopware\Core\Framework\Test\IdsCollection;

/**
 * $builder = (new LayoutBuilder($ids, $key))
 *     ->productSlider('slider', $ids->getList(['product-1', 'product-2', 'product-3']));
 *     ->productThreeColumnBlock('boxes', [
 *         $builder->productBox('box-1', $ids->get('product-1')),
 *         $builder->productBox('box-2', $ids->get('product-2')),
 *         $builder->productBox('box-3', $ids->get('product-3'))
 *     ]);
 */
class LayoutBuilder
{
    protected IdsCollection $ids;

    protected string $id;

    protected ?string $name;

    protected string $type;

    protected array $_dynamic = [];

    protected array $blocks;

    protected array $sections = [];

    public function __construct(IdsCollection $ids, string $key, string $type = 'landingpage')
    {
        $this->ids = $ids;
        $this->id = $this->ids->create($key);
        $this->name = $key;
        $this->type = $type;
    }

    public function build(): array
    {
        $data = get_object_vars($this);

        unset($data['ids'], $data['_dynamic']);

        $data = array_merge($data, $this->_dynamic);

        $data['sections'] = array_values($data['sections']);

        return array_filter($data);
    }

    public function productThreeColumnBlock(array $keys, string $section = 'main'): LayoutBuilder
    {
        $this->ensureSection($section);

        $this->sections[$section]['blocks'][] = [
            'position' => \count($this->sections[$section]['blocks']),
            'type' => 'product-three-column',
            'slots' => [
                array_merge(['slot' => 'left'], $this->productBox($keys[0])),
                array_merge(['slot' => 'center'], $this->productBox($keys[1])),
                array_merge(['slot' => 'right'], $this->productBox($keys[2])),
            ],
        ];

        return $this;
    }

    public function listing(string $section = 'main'): LayoutBuilder
    {
        $this->ensureSection($section);

        $this->sections[$section]['blocks'][] = [
            'position' => \count($this->sections[$section]['blocks']),
            'type' => 'product-listing',
            'sectionPosition' => 'main',
            'marginTop' => '20px',
            'marginBottom' => '20px',
            'marginLeft' => '20px',
            'marginRight' => '20px',
            'slots' => [
                ['type' => 'product-listing', 'slot' => 'content', 'config' => []],
            ],
        ];

        return $this;
    }

    public function productSlider(array $keys, string $section = 'main'): self
    {
        $this->ensureSection($section);

        $this->sections[$section]['blocks'][] = [
            'type' => 'product-slider',
            'position' => \count($this->sections[$section]['blocks']),
            'sectionPosition' => 'main',
            'marginTop' => '20px',
            'marginBottom' => '20px',
            'marginLeft' => '20px',
            'marginRight' => '20px',
            'backgroundMediaMode' => 'cover',
            'slots' => [
                [
                    'type' => 'product-slider',
                    'slot' => 'productSlider',
                    'config' => [
                        'products' => [
                            'source' => 'static',
                            'value' => array_values($this->ids->getList($keys)),
                        ],
                        'title' => ['source' => 'static', 'value' => ''],
                        'displayMode' => ['source' => 'static', 'value' => 'standard'],
                        'boxLayout' => ['source' => 'static', 'value' => 'standard'],
                        'navigation' => ['source' => 'static', 'value' => true],
                        'rotate' => ['source' => 'static', 'value' => false],
                        'border' => ['source' => 'static', 'value' => false],
                        'elMinWidth' => ['source' => 'static', 'value' => '300px'],
                        'verticalAlign' => ['source' => 'static', 'value' => null],
                        'productStreamSorting' => ['source' => 'static', 'value' => 'name:ASC'],
                        'productStreamLimit' => ['source' => 'static', 'value' => 10],
                    ],
                ],
            ],
        ];

        return $this;
    }

    public function productBox(string $key, string $boxLayout = 'standard', string $displayMode = 'standard'): array
    {
        return [
            'type' => 'product-box',
            'config' => [
                'product' => ['source' => 'static', 'value' => $this->ids->get($key)],
                'boxLayout' => ['source' => 'static', 'value' => $boxLayout],
                'displayMode' => ['source' => 'static', 'value' => $displayMode],
                'verticalAlign' => ['source' => 'static', 'value' => null],
            ],
        ];
    }

    private function ensureSection(string $section): void
    {
        if (isset($this->sections[$section])) {
            return;
        }

        $this->sections[$section] = [
            'type' => 'default',
            'position' => \count($this->sections),
            'blocks' => [],
        ];
    }
}
