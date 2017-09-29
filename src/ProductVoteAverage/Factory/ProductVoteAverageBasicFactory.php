<?php declare(strict_types=1);

namespace Shopware\ProductVoteAverage\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\ExtensionRegistryInterface;
use Shopware\Framework\Factory\Factory;
use Shopware\ProductVoteAverage\Extension\ProductVoteAverageExtension;
use Shopware\ProductVoteAverage\Struct\ProductVoteAverageBasicStruct;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;

class ProductVoteAverageBasicFactory extends Factory
{
    const ROOT_NAME = 'product_vote_average_ro';
    const EXTENSION_NAMESPACE = 'productVoteAverage';

    const FIELDS = [
       'uuid' => 'uuid',
       'productUuid' => 'product_uuid',
       'shopUuid' => 'shop_uuid',
       'average' => 'average',
       'total' => 'total',
       'fivePointCount' => 'five_point_count',
       'fourPointCount' => 'four_point_count',
       'threePointCount' => 'three_point_count',
       'twoPointCount' => 'two_point_count',
       'onePointCount' => 'one_point_count',
    ];

    public function __construct(
        Connection $connection,
        ExtensionRegistryInterface $registry
    ) {
        parent::__construct($connection, $registry);
    }

    public function hydrate(
        array $data,
        ProductVoteAverageBasicStruct $productVoteAverage,
        QuerySelection $selection,
        TranslationContext $context
    ): ProductVoteAverageBasicStruct {
        $productVoteAverage->setUuid((string) $data[$selection->getField('uuid')]);
        $productVoteAverage->setProductUuid((string) $data[$selection->getField('productUuid')]);
        $productVoteAverage->setShopUuid((string) $data[$selection->getField('shopUuid')]);
        $productVoteAverage->setAverage((float) $data[$selection->getField('average')]);
        $productVoteAverage->setTotal((int) $data[$selection->getField('total')]);
        $productVoteAverage->setFivePointCount((int) $data[$selection->getField('fivePointCount')]);
        $productVoteAverage->setFourPointCount((int) $data[$selection->getField('fourPointCount')]);
        $productVoteAverage->setThreePointCount((int) $data[$selection->getField('threePointCount')]);
        $productVoteAverage->setTwoPointCount((int) $data[$selection->getField('twoPointCount')]);
        $productVoteAverage->setOnePointCount((int) $data[$selection->getField('onePointCount')]);

        /** @var $extension ProductVoteAverageExtension */
        foreach ($this->getExtensions() as $extension) {
            $extension->hydrate($productVoteAverage, $data, $selection, $context);
        }

        return $productVoteAverage;
    }

    public function getFields(): array
    {
        $fields = array_merge(self::FIELDS, parent::getFields());

        return $fields;
    }

    public function joinDependencies(QuerySelection $selection, QueryBuilder $query, TranslationContext $context): void
    {
        $this->joinTranslation($selection, $query, $context);

        $this->joinExtensionDependencies($selection, $query, $context);
    }

    public function getAllFields(): array
    {
        $fields = array_merge(self::FIELDS, $this->getExtensionFields());

        return $fields;
    }

    protected function getRootName(): string
    {
        return self::ROOT_NAME;
    }

    protected function getExtensionNamespace(): string
    {
        return self::EXTENSION_NAMESPACE;
    }

    private function joinTranslation(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if (!($translation = $selection->filter('translation'))) {
            return;
        }
        $query->leftJoin(
            $selection->getRootEscaped(),
            'product_vote_average_ro_translation',
            $translation->getRootEscaped(),
            sprintf(
                '%s.product_vote_average_ro_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
                $translation->getRootEscaped(),
                $selection->getRootEscaped(),
                $translation->getRootEscaped()
            )
        );
        $query->setParameter('languageUuid', $context->getShopUuid());
    }
}
