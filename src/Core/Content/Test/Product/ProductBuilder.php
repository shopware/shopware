<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product;

use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * How to use:
 *
 * $x = (new ProductBuilder(new IdsCollection(), 'p1'))
 *          ->price(Defaults::CURRENCY, 100)
 *          ->prices(Defaults::CURRENCY, 'rule-1', 100)
 *          ->manufacturer('m1')
 *          ->build();
 */
class ProductBuilder
{
    protected IdsCollection $ids;

    protected string $productNumber;

    protected string $id;

    protected ?string $name;

    protected ?array $manufacturer;

    protected ?array $tax;

    protected bool $active = true;

    protected array $price = [];

    protected array $prices = [];

    protected array $categories = [];

    protected array $properties = [];

    protected int $stock;

    protected ?string $releaseDate;

    protected array $customFields = [];

    protected array $visibilities = [];

    protected ?array $purchasePrices;

    protected ?float $purchasePrice;

    protected ?string $parentId;

    protected array $_dynamic = [];

    protected array $children = [];

    protected array $translations = [];

    protected array $reviews = [];

    public function __construct(IdsCollection $ids, string $number, int $stock = 1, string $taxKey = 't1')
    {
        $this->ids = $ids;
        $this->productNumber = $number;
        $this->id = $this->ids->create($number);
        $this->stock = $stock;
        $this->name = $number;
        $this->tax($taxKey);
    }

    public function parent(string $key): self
    {
        $this->parentId = $this->ids->get($key);

        return $this;
    }

    public function name(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function tax(?string $key, int $rate = 15): self
    {
        if ($key === null) {
            $this->tax = null;

            return $this;
        }

        $this->tax = [
            'id' => $this->ids->create($key),
            'name' => 'test',
            'taxRate' => $rate,
        ];

        return $this;
    }

    public function variant(array $data): self
    {
        $this->children[] = $data;

        return $this;
    }

    public function manufacturer(string $key, array $translations = []): self
    {
        $this->manufacturer = [
            'id' => $this->ids->create($key),
            'name' => $key,
            'translations' => $translations,
        ];

        return $this;
    }

    public function releaseDate(string $releaseDate): self
    {
        $this->releaseDate = $releaseDate;

        return $this;
    }

    public function visibility(string $salesChannelId = Defaults::SALES_CHANNEL, int $visibility = ProductVisibilityDefinition::VISIBILITY_ALL): self
    {
        $this->visibilities[$salesChannelId] = ['salesChannelId' => $salesChannelId, 'visibility' => $visibility];

        return $this;
    }

    public function purchasePrice(float $price): self
    {
        $this->purchasePrice = $price;
        $this->purchasePrices[] = ['currencyId' => Defaults::CURRENCY, 'gross' => $price, 'net' => $price / 115 * 100, 'linked' => false];

        return $this;
    }

    public function price(float $gross, ?float $net = null, string $currencyKey = 'default', ?float $listPriceGross = null, ?float $listPriceNet = null): self
    {
        $net = $net ?? $gross / 115 * 100;

        $price = [
            'gross' => $gross,
            'net' => $net,
            'linked' => false,
        ];

        if ($listPriceGross !== null) {
            $listPriceNet = $listPriceNet ?? $listPriceGross / 115 * 100;

            $price['listPrice'] = [
                'gross' => $listPriceGross,
                'net' => $listPriceNet,
                'linked' => false,
            ];
        }

        $price = $this->buildCurrencyPrice($currencyKey, $price);

        $this->price[$currencyKey] = $price;

        return $this;
    }

    public function prices(string $ruleKey, float $gross, string $currencyKey = 'default', ?float $net = null, int $start = 1, bool $valid = false): self
    {
        $net = $net ?? $gross / 115 * 100;

        $ruleId = $this->ids->create($ruleKey);

        // add to existing price - if exists
        foreach ($this->prices as &$price) {
            if ($price['rule']['id'] !== $ruleId) {
                continue;
            }
            if ($price['quantityStart'] !== $start) {
                continue;
            }

            $raw = ['gross' => $gross, 'net' => $net, 'linked' => false];

            $price['price'][] = $this->buildCurrencyPrice($currencyKey, $raw);

            return $this;
        }

        unset($price);

        $price = ['gross' => $gross, 'net' => $net, 'linked' => false];

        $this->prices[] = [
            'quantityStart' => $start,
            'rule' => [
                'id' => $this->ids->create($ruleKey),
                'priority' => 1,
                'name' => 'test',
                'conditions' => $this->getRuleConditions($valid),
            ],
            'price' => [
                $this->buildCurrencyPrice($currencyKey, $price),
            ],
        ];

        return $this;
    }

    public function category(string $key): self
    {
        $this->categories[] = ['id' => $this->ids->create($key), 'name' => $key];

        return $this;
    }

    /**
     * @param array|object|string|float|int|bool|null $value
     */
    public function customField(string $key, $value): self
    {
        $this->customFields[$key] = $value;

        return $this;
    }

    /**
     * @param array|object|string|float|int|bool|null $value
     */
    public function add(string $key, $value): self
    {
        $this->_dynamic[$key] = $value;

        return $this;
    }

    public function build(): array
    {
        $this->fixPricesQuantity();

        $data = get_object_vars($this);

        unset($data['ids'], $data['_dynamic']);

        $data = array_merge($data, $this->_dynamic);

        return array_filter($data);
    }

    public function property(string $key, string $group): self
    {
        $this->properties[] = [
            'id' => $this->ids->get($key),
            'name' => $key,
            'group' => [
                'id' => $this->ids->get($group),
                'name' => $group,
            ],
        ];

        return $this;
    }

    public function stock(int $stock): self
    {
        $this->stock = $stock;

        return $this;
    }

    public function active(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    /**
     * @param array|object|string|float|int|bool|null $value
     */
    public function translation(string $languageId, string $key, $value): self
    {
        $this->translations[$languageId][$key] = $value;

        return $this;
    }

    public function review(string $title, string $content, float $points = 3, string $salesChannelId = Defaults::SALES_CHANNEL, string $languageId = Defaults::LANGUAGE_SYSTEM): self
    {
        $this->reviews[] = [
            'title' => $title,
            'content' => $content,
            'points' => $points,
            'languageId' => $languageId,
            'salesChannelId' => $salesChannelId,
        ];

        return $this;
    }

    private function getRuleConditions(bool $valid): array
    {
        if ($valid) {
            return [
                [
                    'type' => 'orContainer',
                    'position' => 0,
                    'children' => [
                        [
                            'type' => 'andContainer',
                            'position' => 0,
                            'children' => [
                                ['type' => 'alwaysValid', 'position' => 0],
                            ],
                        ],
                    ],
                ],
            ];
        }

        return [
            [
                'type' => 'orContainer',
                'position' => 0,
                'children' => [
                    [
                        'type' => 'andContainer',
                        'position' => 0,
                        'children' => [
                            [
                                'type' => 'currency',
                                'value' => ['operator' => '=', 'currencyIds' => [Uuid::randomHex()]],
                                'position' => 0,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function fixPricesQuantity(): void
    {
        $grouped = [];
        foreach ($this->prices as $price) {
            $grouped[$price['rule']['id']][] = $price;
        }

        foreach ($grouped as &$group) {
            usort($group, function (array $a, array $b) {
                return $a['quantityStart'] <=> $b['quantityStart'];
            });
        }

        $mapped = [];
        foreach ($grouped as &$group) {
            $group = array_reverse($group);

            $end = null;
            foreach ($group as $price) {
                if ($end !== null) {
                    $price['quantityEnd'] = $end;
                }

                $end = $price['quantityStart'] - 1;

                $mapped[] = $price;
            }
        }

        $this->prices = array_reverse($mapped);
    }

    private function buildCurrencyPrice(string $currencyKey, array $price): array
    {
        if ($currencyKey === 'default') {
            $price['currencyId'] = Defaults::CURRENCY;

            return $price;
        }

        if ($this->ids->has($currencyKey)) {
            $price['currencyId'] = $this->ids->get($currencyKey);

            return $price;
        }

        $price['currency'] = [
            'id' => $this->ids->get($currencyKey),
            'factor' => 2,
            'name' => 'test-currency',
            'shortName' => 'TC',
            'symbol' => '$',
            'isoCode' => 'en-GB',
            'decimalPrecision' => 3,
        ];

        return $price;
    }
}
