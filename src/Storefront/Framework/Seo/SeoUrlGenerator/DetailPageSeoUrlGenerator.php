<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\SeoUrlGenerator;

use Cocur\Slugify\Slugify;
use Shopware\Core\Checkout\Context\CheckoutContextFactoryInterface;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Storefront\Framework\Seo\SeoUrl\SeoUrlEntity;
use Symfony\Component\Routing\RouterInterface;
use Twig\Loader\ArrayLoader;

class DetailPageSeoUrlGenerator extends SeoUrlGenerator
{
    public const ROUTE_NAME = 'frontend.detail.page';
    public const DEFAULT_TEMPLATE = '{{ productName }}/{{ productId }}';

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    public function __construct(
        EntityRepositoryInterface $salesChannelRepository,
        CheckoutContextFactoryInterface $checkoutContextFactory,
        Slugify $slugify,
        RouterInterface $router,
        string $routeName,
        EntityRepositoryInterface $productRepository
    ) {
        parent::__construct($salesChannelRepository, $checkoutContextFactory, $slugify, $router, $routeName);

        $this->productRepository = $productRepository;
    }

    public function getSeoUrlContext(Entity $product): array
    {
        if (!$product instanceof ProductEntity) {
            throw new \InvalidArgumentException('Expected ProductEntity');
        }

        return [
            'product' => $product->jsonSerialize(),

            'id' => $product->getId(),
            'productId' => $product->getId(),
            'productName' => $product->getName(),

            'manufacturerId' => $product->getManufacturer() ? $product->getManufacturer()->getId() : null,
            'manufacturerName' => $product->getManufacturer() ? $product->getManufacturer()->getName() : null,
            'manufacturerNumber' => $product->getManufacturerNumber(),

            'shortId' => $product->getAutoIncrement(),
        ];
    }

    public function generateSeoUrls(string $salesChannelId, array $ids, ?string $template = null): iterable
    {
        $template = $template ?? self::DEFAULT_TEMPLATE;
        $template = "{% autoescape '" . self::ESCAPE_SLUGIFY . "' %}$template{% endautoescape %}";
        $this->twig->setLoader(new ArrayLoader(['template' => $template]));

        $criteria = new Criteria($ids);
        $criteria->addAssociation('manufacturer');

        $seoUrls = [];
        $products = $this->productRepository->search(new Criteria($ids), $this->getContext($salesChannelId));

        /** @var ProductEntity $product */
        foreach ($products as $product) {
            $seoUrl = new SeoUrlEntity();
            $seoUrl->setSalesChannelId($salesChannelId);
            $seoUrl->setForeignKey($product->getId());

            $pathInfo = $this->router->generate(self::ROUTE_NAME, ['productId' => $product->getId()]);
            $seoUrl->setPathInfo($pathInfo);

            $seoPathInfo = $this->twig->render('template', $this->getSeoUrlContext($product));
            $seoUrl->setSeoPathInfo($seoPathInfo);

            $seoUrl->setIsCanonical(true);
            $seoUrl->setIsModified(false);
            $seoUrl->setIsDeleted(false);
            $seoUrl->setIsValid(true);

            $seoUrls[] = $seoUrl;
        }

        return $seoUrls;
    }

    public function getDefaultTemplate(): string
    {
        return self::DEFAULT_TEMPLATE;
    }
}
