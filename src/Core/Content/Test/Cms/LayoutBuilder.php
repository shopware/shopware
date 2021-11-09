<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Cms;

use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Uuid\Uuid;

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
        $this->section($section);

        $this->sections[$section]['blocks'][] = [
            'position' => $this->blockPosition($section),
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
        $this->section($section);

        $this->sections[$section]['blocks'][] = array_merge([
            'position' => $this->blockPosition($section),
            'type' => 'product-listing',
            'sectionPosition' => 'main',
            'slots' => [
                ['type' => 'product-listing', 'slot' => 'content', 'config' => []],
            ],
        ], self::margin(20, 20, 20, 20));

        return $this;
    }

    public function productSlider(array $keys, string $section = 'main'): self
    {
        $this->section($section);

        $this->sections[$section]['blocks'][] = array_merge(
            [
                'type' => 'product-slider',
                'position' => $this->blockPosition($section),
                'sectionPosition' => 'main',
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
            ],
            self::margin(20, 20, 20, 20)
        );

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

    public function section(string $key): void
    {
        if (isset($this->sections[$key])) {
            return;
        }

        $this->sections[$key] = [
            'type' => 'default',
            'position' => \count($this->sections),
            'blocks' => [],
        ];
    }

    public function productHeading(?string $key = null, string $section = 'main'): self
    {
        $this->section($section);
        $key = $key ?? Uuid::randomHex();

        $this->sections[$section]['blocks'][$key] = array_merge(
            [
                'type' => 'product-heading',
                'position' => $this->blockPosition($section),
                'slots' => [
                    ['type' => 'product-name', 'slot' => 'left'],
                    ['type' => 'manufacturer-logo', 'slot' => 'right'],
                ],
            ],
            self::margin(0, 0, 20, 0)
        );

        return $this;
    }

    public function galleryBuybox(?string $key = null, string $section = 'main'): self
    {
        $this->section($section);
        $key = $key ?? Uuid::randomHex();
        $this->sections[$section]['blocks'][$key] = array_merge(
            [
                'type' => 'gallery-buybox',
                'position' => $this->blockPosition($section),
                'slots' => [
                    ['type' => 'image-gallery', 'slot' => 'left'],
                    ['type' => 'buy-box', 'slot' => 'right'],
                ],
            ],
            self::margin(20, 0, 0, 0)
        );

        return $this;
    }

    public function descriptionReviews(?string $key = null, string $section = 'main'): self
    {
        $this->section($section);
        $key = $key ?? Uuid::randomHex();
        $this->sections[$section]['blocks'][$key] = array_merge(
            [
                'type' => 'product-description-reviews',
                'position' => $this->blockPosition($section),
                'slots' => [
                    ['type' => 'product-description-reviews', 'slot' => 'content'],
                ],
            ],
            self::margin(20, 0, 20, 0)
        );

        return $this;
    }

    public function crossSelling(?string $key = null, string $section = 'main'): self
    {
        $this->section($section);
        $key = $key ?? Uuid::randomHex();
        $this->sections[$section]['blocks'][$key] = array_merge(
            [
                'type' => 'cross-selling',
                'position' => $this->blockPosition($section),
                'slots' => [
                    ['type' => 'cross-selling', 'slot' => 'content'],
                ],
            ],
            self::margin(20, 0, 20, 0)
        );

        return $this;
    }

    private function blockPosition(string $section)
    {
        return \count($this->sections[$section]['blocks']);
    }

    private static function margin(int $top, int $right, int $bottom, int $left): array
    {
        return [
            'marginTop' => $top > 0 ? $top . 'px' : '0',
            'marginRight' => $right > 0 ? $right . 'px' : '0',
            'marginBottom' => $bottom > 0 ? $bottom . 'px' : '0',
            'marginLeft' => $left > 0 ? $top . '$left' : '0',
        ];
    }
}
