<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\ContentSystem\DataLoader;

use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Content\Product\SalesChannel\Detail\AbstractProductDetailRoute;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

use function Symfony\Component\String\u;

/**
 * Loads the sales channel product whose id the layout carries — the product detail page's own product by
 * default. The id is read from an element property (default `productId`, which carries the `{{productId}}`
 * placeholder resolved per request), matching the other product loaders.
 *
 * @internal
 *
 * @final
 *
 * @extends AbstractContentDataLoader<SalesChannelProductEntity>
 */
#[Package('inventory')]
class ProductDetailDataLoader extends AbstractContentDataLoader
{
    public const SOURCE = 'product_detail';

    public function __construct(
        private readonly AbstractProductDetailRoute $productDetailRoute
    ) {
    }

    public static function getRequirementType(): string
    {
        return self::SOURCE;
    }

    public function configSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', required: false, hasDefault: true, default: null),
            new ConfigKeySpecification('associations', ConfigKeyKind::Literal, 'list<string>', required: false, hasDefault: true, default: []),
        ]);
    }

    public function load(
        ContentElement $element,
        DataRequirement $requirement,
        SalesChannelContext $context,
        Request $request
    ): ContentDataLoaderResult {
        $config = $requirement->config;

        if (!$config instanceof ProductDetailLoaderConfig) {
            return ContentDataLoaderResult::notFound();
        }

        $propertyName = $config->property ?? 'productId';
        $productId = $element->getProperty($propertyName);

        if (!Uuid::isValid($productId)) {
            return ContentDataLoaderResult::notFound();
        }

        $criteria = $this->buildCriteria($element, $config);

        try {
            $response = $this->productDetailRoute->load($productId, $request, $context, $criteria);
        } catch (ProductException) {
            return ContentDataLoaderResult::notFound();
        }

        return ContentDataLoaderResult::cachedExternally($response->getProduct());
    }

    private function buildCriteria(ContentElement $element, ProductDetailLoaderConfig $config): Criteria
    {
        $criteria = new Criteria();

        foreach ($config->associations as $association) {
            $criteria->addAssociation($association);
        }

        $elementAssociations = $element->getProperty('associations');
        if (\is_array($elementAssociations)) {
            foreach ($elementAssociations as $association) {
                if (\is_string($association)) {
                    $criteria->addAssociation($association);
                }
            }
        }

        return $criteria;
    }
}
