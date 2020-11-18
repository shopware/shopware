<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\FullText\MatchQuery;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
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

    public function __construct(ProductDefinition $definition, EntityMapper $mapper, CashRounding $rounding)
    {
        parent::__construct($mapper);
        $this->definition = $definition;
        $this->rounding = $rounding;
    }

    public function getEntityDefinition(): EntityDefinition
    {
        return $this->definition;
    }

    public function getMapping(Context $context): array
    {
        $definition = $this->definition;

        return [
            '_source' => ['includes' => ['id', 'price']],
            'properties' => array_merge(
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

    public function extendDocuments(array $documents, Context $context): array
    {
        $currencies = $context->getExtension('currencies');

        if (!$currencies instanceof EntityCollection) {
            throw new \RuntimeException('Currencies are required for indexing process');
        }

        foreach ($documents as &$document) {
            $prices = [];

            $purchase = [];
            foreach ($currencies as $currency) {
                $key = 'c_' . $currency->getId();

                $prices[$key] = $this->getCurrencyPrice($document['entity'], $currency);

                $purchase[$key] = $this->getCurrencyPurchasePrice($document['entity'], $currency);
            }

            $document['document']['price'] = $prices;
            $document['document']['purchasePrices'] = $purchase;
        }

        return $documents;
    }

    public function buildTermQuery(Context $context, Criteria $criteria): BoolQuery
    {
        $query = parent::buildTermQuery($context, $criteria);

        $query->add(
            new MatchQuery('description', $criteria->getTerm()),
            BoolQuery::SHOULD
        );

        return $query;
    }

    private function getCurrencyPrice(ProductEntity $entity, CurrencyEntity $currency)
    {
        $origin = $entity->getCurrencyPrice($currency->getId());

        if (!$origin) {
            throw new \RuntimeException(sprintf('Missing default price for product %s', $entity->getProductNumber()));
        }
        $price = clone $origin;

        // fallback price returned?
        if ($price->getCurrencyId() !== $currency->getId()) {
            $price->setGross($price->getGross() * $currency->getFactor());
            $price->setNet($price->getNet() * $currency->getFactor());
        }

        $config = $currency->getItemRounding();

        if (!$config) {
            return json_decode(JsonFieldSerializer::encodeJson($price), true);
        }

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

        $price = clone $origin;

        // fallback price returned?
        if ($price->getCurrencyId() !== $currency->getId()) {
            $price->setGross($price->getGross() * $currency->getFactor());
            $price->setNet($price->getNet() * $currency->getFactor());
        }

        $config = $currency->getItemRounding();

        if (!$config) {
            return json_decode(JsonFieldSerializer::encodeJson($price), true);
        }

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
