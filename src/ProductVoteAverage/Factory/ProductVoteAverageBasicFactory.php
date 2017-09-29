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
       'product_uuid' => 'product_uuid',
       'shop_uuid' => 'shop_uuid',
       'average' => 'average',
       'total' => 'total',
       'five_point_count' => 'five_point_count',
       'four_point_count' => 'four_point_count',
       'three_point_count' => 'three_point_count',
       'two_point_count' => 'two_point_count',
       'one_point_count' => 'one_point_count',
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
        $productVoteAverage->setProductUuid((string) $data[$selection->getField('product_uuid')]);
        $productVoteAverage->setShopUuid((string) $data[$selection->getField('shop_uuid')]);
        $productVoteAverage->setAverage((float) $data[$selection->getField('average')]);
        $productVoteAverage->setTotal((int) $data[$selection->getField('total')]);
        $productVoteAverage->setFivePointCount((int) $data[$selection->getField('five_point_count')]);
        $productVoteAverage->setFourPointCount((int) $data[$selection->getField('four_point_count')]);
        $productVoteAverage->setThreePointCount((int) $data[$selection->getField('three_point_count')]);
        $productVoteAverage->setTwoPointCount((int) $data[$selection->getField('two_point_count')]);
        $productVoteAverage->setOnePointCount((int) $data[$selection->getField('one_point_count')]);

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
