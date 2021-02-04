<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product;

use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\IdsCollection;

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
    /**
     * @var IdsCollection
     */
    protected $ids;

    /**
     * @var string
     */
    protected $productNumber;

    /**
     * @var string
     */
    protected $id;

    /**
     * @var string|null
     */
    protected $name;

    /**
     * @var array|null
     */
    protected $manufacturer;

    /**
     * @var array|null
     */
    protected $tax;

    /**
     * @var array
     */
    protected $price = [];

    /**
     * @var array
     */
    protected $prices = [];

    /**
     * @var array
     */
    protected $categories = [];

    /**
     * @var array
     */
    protected $properties = [];

    /**
     * @var int
     */
    protected $stock;

    /**
     * @var string|null
     */
    protected $releaseDate;

    /**
     * @var array
     */
    protected $customFields = [];

    /**
     * @var array
     */
    protected $visibilities = [];

    /**
     * @var array|null
     */
    protected $purchasePrices;

    /**
     * @var float|null
     */
    protected $purchasePrice;

    /**
     * @var string|null
     */
    protected $parentId;

    /**
     * @var array
     */
    protected $_dynamic = [];

    /**
     * @var array[]
     */
    protected $children = [];

    public function __construct(IdsCollection $ids, string $number, int $stock = 1, string $taxKey = 't1')
    {
        $this->ids = $ids;
        $this->productNumber = $number;
        $this->id = $this->ids->create($number);
        $this->tax($taxKey);
        $this->stock = $stock;
        $this->name = $number;
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

    public function variant(array $data): ProductBuilder
    {
        $this->children[] = $data;

        return $this;
    }

    public function manufacturer(string $key): self
    {
        $this->manufacturer = [
            'id' => $this->ids->create($key),
            'name' => $key,
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
        $this->visibilities[] = ['salesChannelId' => $salesChannelId, 'visibility' => $visibility];

        return $this;
    }

    public function purchasePrice(float $price): self
    {
        $this->purchasePrice = $price;
        $this->purchasePrices = ['currencyId' => Defaults::CURRENCY, 'gross' => $price, 'net' => $price / 115 * 100, 'linked' => false];

        return $this;
    }

    public function price(string $currencyId, float $gross, ?float $net = null): self
    {
        $net = $net ?? $gross / 115 * 100;
        $this->price[] = ['currencyId' => $currencyId, 'gross' => $gross, 'net' => $net, 'linked' => false];

        return $this;
    }

    public function prices(string $currencyId, string $ruleKey, float $gross, ?float $net = null, int $start = 1): self
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

            $price['price'][] = ['currencyId' => $currencyId, 'gross' => $gross, 'net' => $net, 'linked' => false];

            return $this;
        }
        unset($price);

        $this->prices[] = [
            'quantityStart' => 1,
            'rule' => [
                'id' => $this->ids->create($ruleKey),
                'priority' => 1,
                'name' => 'test',
            ],
            'price' => [
                ['currencyId' => $currencyId, 'gross' => $gross, 'net' => $net, 'linked' => false],
            ],
        ];

        return $this;
    }

    public function category(string $key): ProductBuilder
    {
        $this->categories[] = ['id' => $this->ids->create($key), 'name' => $key];

        return $this;
    }

    /**
     * @param array|object|string|float|int|bool|null $value
     */
    public function customField(string $key, $value): ProductBuilder
    {
        $this->customFields[$key] = $value;

        return $this;
    }

    /**
     * @param array|object|string|float|int|bool|null $value
     */
    public function add(string $key, $value): ProductBuilder
    {
        $this->_dynamic[$key] = $value;

        return $this;
    }

    public function build(): array
    {
        $data = get_object_vars($this);

        unset($data['ids'], $data['_dynamic']);

        $data = array_merge($data, $this->_dynamic);

        return array_filter($data);
    }

    public function property(string $key, string $group): ProductBuilder
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
}
