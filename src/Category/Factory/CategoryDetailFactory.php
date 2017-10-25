<?php declare(strict_types=1);

namespace Shopware\Category\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Category\Struct\CategoryBasicStruct;
use Shopware\Category\Struct\CategoryDetailStruct;
use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroup\Factory\CustomerGroupBasicFactory;
use Shopware\Framework\Read\ExtensionRegistryInterface;
use Shopware\Media\Factory\MediaBasicFactory;
use Shopware\Media\Struct\MediaBasicStruct;
use Shopware\Product\Factory\ProductBasicFactory;
use Shopware\ProductStream\Factory\ProductStreamBasicFactory;
use Shopware\ProductStream\Struct\ProductStreamBasicStruct;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Shopware\SeoUrl\Factory\SeoUrlBasicFactory;

class CategoryDetailFactory extends CategoryBasicFactory
{
    /**
     * @var ProductStreamBasicFactory
     */
    protected $productStreamFactory;

    /**
     * @var MediaBasicFactory
     */
    protected $mediaFactory;

    /**
     * @var ProductBasicFactory
     */
    protected $productFactory;

    /**
     * @var CustomerGroupBasicFactory
     */
    protected $customerGroupFactory;

    public function __construct(
        Connection $connection,
        ExtensionRegistryInterface $registry,
        ProductStreamBasicFactory $productStreamFactory,
        MediaBasicFactory $mediaFactory,
        ProductBasicFactory $productFactory,
        CustomerGroupBasicFactory $customerGroupFactory,
        SeoUrlBasicFactory $seoUrlFactory
    ) {
        parent::__construct($connection, $registry, $seoUrlFactory);
        $this->productStreamFactory = $productStreamFactory;
        $this->mediaFactory = $mediaFactory;
        $this->productFactory = $productFactory;
        $this->customerGroupFactory = $customerGroupFactory;
    }

    public function getFields(): array
    {
        $fields = array_merge(parent::getFields(), $this->getExtensionFields());
        $fields['productStream'] = $this->productStreamFactory->getFields();
        $fields['media'] = $this->mediaFactory->getFields();
        $fields['_sub_select_product_uuids'] = '_sub_select_product_uuids';
        $fields['_sub_select_blockedCustomerGroups_uuids'] = '_sub_select_blockedCustomerGroups_uuids';

        return $fields;
    }

    public function hydrate(
        array $data,
        CategoryBasicStruct $category,
        QuerySelection $selection,
        TranslationContext $context
    ): CategoryBasicStruct {
        /** @var CategoryDetailStruct $category */
        $category = parent::hydrate($data, $category, $selection, $context);
        $productStream = $selection->filter('productStream');
        if ($productStream && !empty($data[$productStream->getField('uuid')])) {
            $category->setProductStream(
                $this->productStreamFactory->hydrate($data, new ProductStreamBasicStruct(), $productStream, $context)
            );
        }
        $media = $selection->filter('media');
        if ($media && !empty($data[$media->getField('uuid')])) {
            $category->setMedia(
                $this->mediaFactory->hydrate($data, new MediaBasicStruct(), $media, $context)
            );
        }
        if ($selection->hasField('_sub_select_product_uuids')) {
            $uuids = explode('|', (string) $data[$selection->getField('_sub_select_product_uuids')]);
            $category->setProductUuids(array_values(array_filter($uuids)));
        }

        if ($selection->hasField('_sub_select_blockedCustomerGroups_uuids')) {
            $uuids = explode('|', (string) $data[$selection->getField('_sub_select_blockedCustomerGroups_uuids')]);
            $category->setBlockedCustomerGroupsUuids(array_values(array_filter($uuids)));
        }

        return $category;
    }

    public function joinDependencies(QuerySelection $selection, QueryBuilder $query, TranslationContext $context): void
    {
        parent::joinDependencies($selection, $query, $context);

        $this->joinProductStream($selection, $query, $context);
        $this->joinMedia($selection, $query, $context);
        $this->joinProducts($selection, $query, $context);
        $this->joinBlockedCustomerGroups($selection, $query, $context);
    }

    public function getAllFields(): array
    {
        $fields = parent::getAllFields();
        $fields['productStream'] = $this->productStreamFactory->getAllFields();
        $fields['media'] = $this->mediaFactory->getAllFields();
        $fields['products'] = $this->productFactory->getAllFields();
        $fields['blockedCustomerGroups'] = $this->customerGroupFactory->getAllFields();

        return $fields;
    }

    protected function getExtensionFields(): array
    {
        $fields = parent::getExtensionFields();

        foreach ($this->getExtensions() as $extension) {
            $extensionFields = $extension->getDetailFields();
            foreach ($extensionFields as $key => $field) {
                $fields[$key] = $field;
            }
        }

        return $fields;
    }

    private function joinProductStream(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if (!($productStream = $selection->filter('productStream'))) {
            return;
        }
        $query->leftJoin(
            $selection->getRootEscaped(),
            'product_stream',
            $productStream->getRootEscaped(),
            sprintf('%s.uuid = %s.product_stream_uuid', $productStream->getRootEscaped(), $selection->getRootEscaped())
        );
        $this->productStreamFactory->joinDependencies($productStream, $query, $context);
    }

    private function joinMedia(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if (!($media = $selection->filter('media'))) {
            return;
        }
        $query->leftJoin(
            $selection->getRootEscaped(),
            'media',
            $media->getRootEscaped(),
            sprintf('%s.uuid = %s.media_uuid', $media->getRootEscaped(), $selection->getRootEscaped())
        );
        $this->mediaFactory->joinDependencies($media, $query, $context);
    }

    private function joinProducts(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if ($selection->hasField('_sub_select_product_uuids')) {
            $query->addSelect('
                (
                    SELECT GROUP_CONCAT(mapping.product_uuid SEPARATOR \'|\')
                    FROM product_category_ro mapping
                    WHERE mapping.category_uuid = ' . $selection->getRootEscaped() . '.uuid
                ) as ' . QuerySelection::escape($selection->getField('_sub_select_product_uuids'))
            );
        }

        if (!($products = $selection->filter('products'))) {
            return;
        }

        $mapping = QuerySelection::escape($products->getRoot() . '.mapping');

        $query->leftJoin(
            $selection->getRootEscaped(),
            'product_category_ro',
            $mapping,
            sprintf('%s.uuid = %s.category_uuid', $selection->getRootEscaped(), $mapping)
        );
        $query->leftJoin(
            $mapping,
            'product',
            $products->getRootEscaped(),
            sprintf('%s.product_uuid = %s.uuid', $mapping, $products->getRootEscaped())
        );

        $this->productFactory->joinDependencies($products, $query, $context);

        $query->groupBy(sprintf('%s.uuid', $selection->getRootEscaped()));
    }

    private function joinBlockedCustomerGroups(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if ($selection->hasField('_sub_select_blockedCustomerGroups_uuids')) {
            $query->addSelect('
                (
                    SELECT GROUP_CONCAT(mapping.customer_group_uuid SEPARATOR \'|\')
                    FROM category_avoid_customer_group mapping
                    WHERE mapping.category_uuid = ' . $selection->getRootEscaped() . '.uuid
                ) as ' . QuerySelection::escape($selection->getField('_sub_select_blockedCustomerGroups_uuids'))
            );
        }

        if (!($blockedCustomerGroups = $selection->filter('blockedCustomerGroups'))) {
            return;
        }

        $mapping = QuerySelection::escape($blockedCustomerGroups->getRoot() . '.mapping');

        $query->leftJoin(
            $selection->getRootEscaped(),
            'category_avoid_customer_group',
            $mapping,
            sprintf('%s.uuid = %s.category_uuid', $selection->getRootEscaped(), $mapping)
        );
        $query->leftJoin(
            $mapping,
            'customer_group',
            $blockedCustomerGroups->getRootEscaped(),
            sprintf('%s.customer_group_uuid = %s.uuid', $mapping, $blockedCustomerGroups->getRootEscaped())
        );

        $this->customerGroupFactory->joinDependencies($blockedCustomerGroups, $query, $context);

        $query->groupBy(sprintf('%s.uuid', $selection->getRootEscaped()));
    }
}
