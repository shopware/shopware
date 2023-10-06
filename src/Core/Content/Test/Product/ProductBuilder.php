<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product;

use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Test\Cms\LayoutBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestBuilderTrait;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 * How to use:
 *
 * $x = (new ProductBuilder(new IdsCollection(), 'p1'))
 *          ->price(Defaults::CURRENCY, 100)
 *          ->prices(Defaults::CURRENCY, 'rule-1', 100)
 *          ->manufacturer('m1')
 *          ->slot('my-slot', ['media' => ['source' => 'static', value => 'some-uuid']])
 *          ->build();
 *
 * @phpstan-type RuleConditions array{array{type: 'orContainer', position: 0, children: array{array{type: 'andContainer', position: 0, children: array{array{type: 'currency'|'alwaysValid', value?: array{operator: '=', currencyIds: array{string}}, position: 0}}}}}}
 * @phpstan-type CurrencyPrice array{gross: float, net: float, linked: bool, listPrice?: float|array{gross: float, net: float, linked: bool}, currencyId: string}
 * @phpstan-type Manufacturer array{id: string, name: string, translations: array<string, array{name: string}>}|null
 * @phpstan-type Tax array{id: string, name: string, taxRate: int}|null
 * @phpstan-type Price array{gross: float, net: float, linked: bool, listPrice: array{gross: float, net: float, linked: bool}}|array{}
 * @phpstan-type Properties array<array{id: string} | array{id: string, name: string, group: array{id: string, name: string}}>
 * @phpstan-type Visibilities array<string, array{salesChannelId: string, visibility: int}>
 * @phpstan-type ProductReviews array<array{title: string, content: string, points: float, languageId: string, salesChannelId: string, status: bool, customerId: string|null}>
 * @phpstan-type ConfiguratorSettings array<array{option: array{id: string, name: string, group: array{id: string, name: string}}}>
 * @phpstan-type Options array<array{id: string, name: string, position: int, group: array{id: string, name: string}} | array{id: string}>
 * @phpstan-type Media array<array{id: string, position: int, media: array{fileName: string}}>
 * @phpstan-type CrossSellings array<array{id: string, name: string, sortBy: string, sortDirection: 'ASC'|'DESC', active: bool, type: 'productStream', productStreamId?: string, productStream?: array{id: string, name: string, filters: mixed}}>
 */
class ProductBuilder
{
    use TestBuilderTrait {
        build as private parentBuild;
    }

    public string $id;

    protected ?string $name;

    /**
     * @var Manufacturer
     */
    protected ?array $manufacturer;

    /**
     * @var Tax
     */
    protected ?array $tax;

    protected bool $active = true;

    /**
     * @var Price
     */
    protected array $price = [];

    /**
     * @var array<array<mixed>>
     */
    protected array $prices = [];

    /**
     * @var array<array{id: string, name: string}>
     */
    protected array $categories = [];

    /**
     * @var Properties
     */
    protected array $properties = [];

    protected ?string $releaseDate = null;

    /**
     * @var array<string, mixed>
     */
    protected array $customFields = [];

    /**
     * @var Visibilities
     */
    protected array $visibilities = [];

    /**
     * @var array<CurrencyPrice>|null
     */
    protected ?array $purchasePrices;

    protected ?float $purchasePrice = null;

    protected ?string $parentId = null;

    /**
     * @var array<array<mixed>>
     */
    protected array $children = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $translations = [];

    /**
     * @var ProductReviews
     */
    protected array $productReviews = [];

    protected ?bool $isCloseout = false;

    /**
     * @var ConfiguratorSettings
     */
    protected array $configuratorSettings = [];

    /**
     * @var Options
     */
    protected array $options = [];

    /**
     * @var Media
     */
    protected array $media = [];

    protected ?string $coverId = null;

    /**
     * @var array{id: string}|array<mixed>|null
     */
    protected ?array $cmsPage = null;

    /**
     * @var CrossSellings
     */
    protected array $crossSellings = [];

    /**
     * @var array<string, array{id: string, name: string}>
     */
    protected array $tags = [];

    protected null|string $createdAt;

    /**
     * @var array<string, array<array<mixed>>>
     */
    private array $dependencies = [];

    public function __construct(
        IdsCollection $ids,
        protected string $productNumber,
        protected int $stock = 1,
        string $taxKey = 't1'
    ) {
        $this->ids = $ids;
        $this->id = $this->ids->create($productNumber);
        $this->name = $productNumber;
        $this->tax($taxKey);
    }

    /**
     * @return array<mixed>
     */
    public function build(): array
    {
        $this->fixPricesQuantity();

        return $this->parentBuild();
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
            'name' => $key,
            'taxRate' => $rate,
        ];

        return $this;
    }

    /**
     * @param array<mixed> $data
     */
    public function variant(array $data): self
    {
        $this->children[] = $data;

        return $this;
    }

    /**
     * @param array<string, array{name: string}> $translations
     */
    public function manufacturer(string $key, array $translations = []): self
    {
        $this->manufacturer = [
            'id' => $this->ids->get($key),
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

    public function visibility(string $salesChannelId = TestDefaults::SALES_CHANNEL, int $visibility = ProductVisibilityDefinition::VISIBILITY_ALL): self
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

    public function price(float $gross, ?float $net = null, string $currencyKey = 'default', ?float $listPriceGross = null, ?float $listPriceNet = null, bool $linked = false): self
    {
        $net ??= $gross / 115 * 100;

        $price = [
            'gross' => $gross,
            'net' => $net,
            'linked' => $linked,
        ];

        if ($listPriceGross !== null) {
            $listPriceNet ??= $listPriceGross / 115 * 100;

            $price['listPrice'] = [
                'gross' => $listPriceGross,
                'net' => $listPriceNet,
                'linked' => $linked,
            ];
        }

        $price = $this->buildCurrencyPrice($currencyKey, $price);

        $this->price[$currencyKey] = $price;

        return $this;
    }

    public function prices(string $ruleKey, float $gross, string $currencyKey = 'default', ?float $net = null, int $start = 1, bool $valid = false, ?float $listPriceGross = null, ?float $listPriceNet = null): self
    {
        $net ??= $gross / 115 * 100;

        $listPrice = null;
        if ($listPriceGross !== null) {
            $listPriceNet ??= $listPriceGross / 115 * 100;

            $listPrice = [
                'gross' => $listPriceGross,
                'net' => $listPriceNet,
                'linked' => false,
            ];
        }

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

            if ($listPrice !== null) {
                $raw['listPrice'] = $listPrice;
            }

            $price['price'][] = $this->buildCurrencyPrice($currencyKey, $raw);

            return $this;
        }

        unset($price);

        $price = ['gross' => $gross, 'net' => $net, 'linked' => false];

        if ($listPrice !== null) {
            $price['listPrice'] = $listPrice;
        }

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
     * @param array<string> $keys
     */
    public function categories(array $keys): self
    {
        array_map($this->category(...), $keys);

        return $this;
    }

    /**
     * @param mixed $value
     */
    public function customField(string $key, $value): self
    {
        $this->customFields[$key] = $value;

        return $this;
    }

    public function property(string $key, string $group): self
    {
        if ($this->ids->has($key)) {
            $this->properties[] = ['id' => $this->ids->get($key)];

            return $this;
        }

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
     * @param mixed $value
     */
    public function translation(string $languageId, string $key, $value): self
    {
        $this->translations[$languageId][$key] = $value;

        return $this;
    }

    public function review(
        string $title,
        string $content,
        float $points = 3,
        string $salesChannelId = TestDefaults::SALES_CHANNEL,
        string $languageId = Defaults::LANGUAGE_SYSTEM,
        bool $status = true,
        ?string $customerId = null
    ): self {
        $this->productReviews[] = [
            'title' => $title,
            'content' => $content,
            'points' => $points,
            'languageId' => $languageId,
            'salesChannelId' => $salesChannelId,
            'status' => $status,
            'customerId' => $customerId,
        ];

        return $this;
    }

    public function closeout(?bool $state = true): ProductBuilder
    {
        $this->isCloseout = $state;

        return $this;
    }

    public function configuratorSetting(string $key, string $group): self
    {
        $this->configuratorSettings[] = [
            'option' => [
                'id' => $this->ids->get($key),
                'name' => $key,
                'group' => [
                    'id' => $this->ids->get($group),
                    'name' => $group,
                ],
            ],
        ];

        return $this;
    }

    public function option(string $key, string $group, int $position = 1): self
    {
        if ($this->ids->has($key)) {
            $this->options[] = ['id' => $this->ids->get($key)];

            return $this;
        }

        $this->options[] = [
            'id' => $this->ids->get($key),
            'name' => $key,
            'position' => $position,
            'group' => [
                'id' => $this->ids->get($group),
                'name' => $group,
            ],
        ];

        return $this;
    }

    public function media(string $key, int $position = 0): ProductBuilder
    {
        $this->media[] = [
            'id' => $this->ids->get($key),
            'position' => $position,
            'media' => ['fileName' => $key],
        ];

        return $this;
    }

    public function cover(string $key): ProductBuilder
    {
        $this->media[] = [
            'id' => $this->ids->get($key),
            'position' => -1,
            'media' => ['fileName' => $key],
        ];

        $this->coverId = $this->ids->get($key);

        return $this;
    }

    public function layout(string $key): self
    {
        if ($this->ids->has($key)) {
            $this->cmsPage = ['id' => $this->ids->get($key)];

            return $this;
        }

        $this->cmsPage = (new LayoutBuilder($this->ids, $key, 'detailpage'))
            ->productHeading()
            ->galleryBuybox()
            ->descriptionReviews()
            ->crossSelling()
            ->build();

        return $this;
    }

    /**
     * @param array<mixed> $value
     */
    public function slot(string $key, array $value, string $languageId = Defaults::LANGUAGE_SYSTEM): self
    {
        if (isset($this->translations[$languageId]['slotConfig']) && \is_array($this->translations[$languageId]['slotConfig'])) {
            $slotConfig = $this->translations[$languageId]['slotConfig'];
        } else {
            $slotConfig = [];
        }

        $slotConfig[$this->ids->get($key)] = $value;

        $this->translation(
            $languageId,
            'slotConfig',
            $slotConfig
        );

        return $this;
    }

    public function crossSelling(string $key, string $stream, string $sort = '+name'): self
    {
        $crossSelling = [
            'id' => $this->ids->get($key),
            'name' => $key,
            'sortBy' => substr($sort, 1),
            'sortDirection' => $sort[0] === '+' ? 'ASC' : 'DESC',
            'active' => true,
            'type' => 'productStream',
        ];

        if ($this->ids->has($stream)) {
            $crossSelling['productStreamId'] = $this->ids->get($stream);
        } else {
            $crossSelling['productStream'] = [
                'id' => $this->ids->get($stream),
                'name' => $stream,
                'filters' => [
                    ['type' => 'multi', 'operator' => 'OR', 'position' => 0, 'queries' => [
                        ['type' => 'multi', 'operator' => 'AND', 'position' => 0, 'queries' => [
                            ['type' => 'equals', 'field' => 'active', 'value' => '1', 'position' => 0],
                        ]],
                    ]],
                ],
            ];
        }

        $this->crossSellings[] = $crossSelling;

        return $this;
    }

    public function tag(string $key): self
    {
        $this->tags[$key] = ['id' => $this->ids->get($key), 'name' => $key];

        return $this;
    }

    public function write(ContainerInterface $container): void
    {
        $container->get('product.repository')->create([$this->build()], Context::createDefaultContext());

        $this->writeDependencies($container);
    }

    public function writeDependencies(ContainerInterface $container): void
    {
        foreach ($this->dependencies as $entity => $records) {
            /** @var EntityRepository $repository */
            $repository = $container->get($entity . '.repository');

            $repository->create($records, Context::createDefaultContext());
        }
    }

    public function createdAt(string|\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt instanceof \DateTimeImmutable ? $createdAt->format(Defaults::STORAGE_DATE_TIME_FORMAT) : $createdAt;

        return $this;
    }

    /**
     * @return RuleConditions
     */
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
            usort($group, fn (array $a, array $b) => $a['quantityStart'] <=> $b['quantityStart']);
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

    /**
     * @param array{gross: float, net: float, linked: bool, listPrice?: array{gross: float, net: float, linked: bool}} $price
     *
     * @return CurrencyPrice
     */
    private function buildCurrencyPrice(string $currencyKey, array $price): array
    {
        if ($currencyKey === 'default') {
            $price['currencyId'] = Defaults::CURRENCY;

            return $price;
        }

        $price['currencyId'] = $this->ids->get($currencyKey);

        $this->dependencies['currency'][] = [
            'id' => $this->ids->get($currencyKey),
            'factor' => 2,
            'name' => 'test-currency',
            'shortName' => 'TC',
            'symbol' => '$',
            'isoCode' => 'en',
            'decimalPrecision' => 2,
            'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
        ];

        return $price;
    }
}
