<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Cms;

use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
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
    protected string $id;

    protected ?string $name;

    /**
     * @var mixed[]
     */
    protected array $_dynamic = [];

    /**
     * @var mixed[]
     */
    protected array $blocks;

    /**
     * @var mixed[]
     */
    protected array $sections = [];

    public function __construct(
        protected IdsCollection $ids,
        string $key,
        protected string $type = 'landingpage'
    ) {
        $this->id = $this->ids->create($key);
        $this->name = $key;
    }

    /**
     * @return mixed[]
     */
    public function build(): array
    {
        $data = get_object_vars($this);

        unset($data['ids'], $data['_dynamic']);

        $data = array_merge($data, $this->_dynamic);

        $data['sections'] = array_values($data['sections']);

        return array_filter($data);
    }

    /**
     * @param string[] $keys
     */
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

    /**
     * @param string[] $keys
     */
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

    public function productStreamSlider(string $stream, string $section = 'main'): self
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
                                'source' => 'product_stream',
                                'value' => $this->ids->get($stream),
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

    /**
     * @return mixed[]
     */
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
        $key ??= Uuid::randomHex();

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
        $key ??= Uuid::randomHex();
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
        $key ??= Uuid::randomHex();
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
        $key ??= Uuid::randomHex();
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

    private function blockPosition(string $section): int
    {
        return is_countable($this->sections[$section]['blocks']) ? \count($this->sections[$section]['blocks']) : 0;
    }

    /**
     * @return string[]
     */
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
