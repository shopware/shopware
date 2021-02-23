<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use Doctrine\DBAL\Connection;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\FullText\MatchQuery;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopware\Elasticsearch\Framework\Indexing\EntityMapper;

class ElasticsearchProductDefinition extends AbstractElasticsearchDefinition
{
    /**
     * @var ProductDefinition
     */
    protected $definition;

    /**
     * @var CashRounding
     */
    private $rounding;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(ProductDefinition $definition, EntityMapper $mapper, Connection $connection, CashRounding $rounding)
    {
        parent::__construct($mapper);
        $this->definition = $definition;
        $this->rounding = $rounding;
        $this->connection = $connection;
    }

    public function getEntityDefinition(): EntityDefinition
    {
        return $this->definition;
    }

    public function getMapping(Context $context): array
    {
        $definition = $this->definition;

        return [
            '_source' => ['includes' => ['id']],
            'properties' => array_replace(
                $this->mapper->mapFields($definition, $context),
                [
                    'categoriesRo' => $this->mapper->mapField($definition, $definition->getField('categoriesRo'), $context),
                    'properties' => $this->mapper->mapField($definition, $definition->getField('properties'), $context),
                    'manufacturer' => $this->mapper->mapField($definition, $definition->getField('manufacturer'), $context),
                    'tags' => $this->mapper->mapField($definition, $definition->getField('tags'), $context),
                    'options' => $this->mapper->mapField($definition, $definition->getField('options'), $context),
                    'visibilities' => $this->mapper->mapField($definition, $definition->getField('visibilities'), $context),
                    'configuratorSettings' => $this->mapper->mapField($definition, $definition->getField('configuratorSettings'), $context),
                ]
            ),
            'dynamic_templates' => [
                [
                    'cheapest_price' => [
                        'match' => 'cheapest_price_rule*',
                        'mapping' => ['type' => 'double'],
                    ],
                ],
            ],
        ];
    }

    public function extendCriteria(Criteria $criteria): void
    {
        $criteria
            ->addAssociation('categoriesRo')
            ->addAssociation('properties')
            ->addAssociation('manufacturer')
            ->addAssociation('tags')
            ->addAssociation('configuratorSettings')
            ->addAssociation('options')
            ->addAssociation('visibilities')
        ;
    }

    public function extendDocuments(EntityCollection $collection, array $documents, Context $context): array
    {
        $currencies = $context->getExtension('currencies');

        if (!$currencies instanceof EntityCollection) {
            throw new \RuntimeException('Currencies are required for indexing process');
        }

        foreach ($documents as &$document) {
            $prices = [];

            $purchase = [];
            foreach ($currencies as $currency) {
                $entity = $collection->get($document['id']);

                $key = 'c_' . $currency->getId();

                $prices[$key] = $this->getCurrencyPrice($entity, $currency);

                $purchase[$key] = $this->getCurrencyPurchasePrice($entity, $currency);
            }

            $document['price'] = $prices;
            $document['purchasePrices'] = $purchase;
        }

        return $this->mapCheapestPrices($collection, $documents);
    }

    public function buildTermQuery(Context $context, Criteria $criteria): BoolQuery
    {
        $query = parent::buildTermQuery($context, $criteria);

        $query->add(
            new MatchQuery('description', (string) $criteria->getTerm()),
            BoolQuery::SHOULD
        );

        return $query;
    }

    private function mapCheapestPrices(EntityCollection $collection, array $documents): array
    {
        if (!Feature::isActive('FEATURE_NEXT_10553')) {
            return $documents;
        }

        $prices = $this->connection->fetchAll(
            '
            SELECT LOWER(HEX(variant.id)) as id, variant.cheapest_price_accessor
            FROM product variant
            WHERE variant.id IN (:ids)
        ',
            ['ids' => Uuid::fromHexToBytesList($collection->getIds())],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        $prices = FetchModeHelper::keyPair($prices);

        foreach ($documents as &$document) {
            $id = $document['id'];

            if (!isset($prices[$id])) {
                continue;
            }

            $price = json_decode($prices[$id], true);
            foreach ($price as $rule => $currencies) {
                foreach ($currencies as $currency => $taxes) {
                    $key = 'cheapest_price_' . $rule . '_' . $currency . '_gross';
                    $document[$key] = $taxes['gross'];

                    $key = 'cheapest_price_' . $rule . '_' . $currency . '_net';
                    $document[$key] = $taxes['net'];
                }
            }
        }

        return $documents;
    }

    private function getCurrencyPrice(ProductEntity $entity, CurrencyEntity $currency): array
    {
        $origin = $entity->getCurrencyPrice($currency->getId());

        if (!$origin) {
            throw new \RuntimeException(sprintf('Missing default price for product %s', $entity->getProductNumber()));
        }

        return $this->getPrice($origin, $currency);
    }

    private function getCurrencyPurchasePrice(ProductEntity $entity, CurrencyEntity $currency): array
    {
        $prices = $entity->getPurchasePrices();

        if (!$prices) {
            return [];
        }

        $origin = $prices->getCurrencyPrice($currency->getId());

        if (!$origin) {
            return [];
        }

        return $this->getPrice(clone $origin, $currency);
    }

    private function getPrice(Price $origin, CurrencyEntity $currency): array
    {
        $price = clone $origin;

        // fallback price returned?
        if ($price->getCurrencyId() !== $currency->getId()) {
            $price->setGross($price->getGross() * $currency->getFactor());
            $price->setNet($price->getNet() * $currency->getFactor());
        }

        $config = $currency->getItemRounding();

        $price->setGross(
            $this->rounding->cashRound($price->getGross(), $config)
        );

        if ($config->roundForNet()) {
            $price->setNet(
                $this->rounding->cashRound($price->getNet(), $config)
            );
        }

        return json_decode(JsonFieldSerializer::encodeJson($price), true);
    }
}
