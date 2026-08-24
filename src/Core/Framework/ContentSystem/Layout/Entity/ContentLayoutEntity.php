<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Entity;

use Shopware\Core\Content\Category\Aggregate\CategoryContentLayout\CategoryContentLayoutCollection;
use Shopware\Core\Content\LandingPage\Aggregate\LandingPageContentLayout\LandingPageContentLayoutCollection;
use Shopware\Core\Content\Product\Aggregate\ProductContentLayout\ProductContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('framework')]
class ContentLayoutEntity extends Entity
{
    use EntityIdTrait;

    protected string $name;

    protected string $version;

    /**
     * @var list<StoredElement>
     */
    protected array $layout;

    // Always hydrated: root_source is a Required field, so every full load sets it. A future PartialEntity /
    // addFields reader that omits it would make getRootSource() throw on the uninitialized typed property.
    protected string $rootSource;

    protected ?ProductContentLayoutCollection $productContentLayouts = null;

    protected ?CategoryContentLayoutCollection $categoryContentLayouts = null;

    protected ?LandingPageContentLayoutCollection $landingPageContentLayouts = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function setVersion(string $version): void
    {
        $this->version = $version;
    }

    /**
     * @return list<StoredElement>
     */
    public function getLayout(): array
    {
        return $this->layout;
    }

    /**
     * @param list<StoredElement> $layout
     */
    public function setLayout(array $layout): void
    {
        $this->layout = $layout;
    }

    public function getRootSource(): string
    {
        return $this->rootSource;
    }

    public function setRootSource(string $rootSource): void
    {
        $this->rootSource = $rootSource;
    }

    public function getProductContentLayouts(): ?ProductContentLayoutCollection
    {
        return $this->productContentLayouts;
    }

    public function setProductContentLayouts(ProductContentLayoutCollection $productContentLayouts): void
    {
        $this->productContentLayouts = $productContentLayouts;
    }

    public function getCategoryContentLayouts(): ?CategoryContentLayoutCollection
    {
        return $this->categoryContentLayouts;
    }

    public function setCategoryContentLayouts(CategoryContentLayoutCollection $categoryContentLayouts): void
    {
        $this->categoryContentLayouts = $categoryContentLayouts;
    }

    public function getLandingPageContentLayouts(): ?LandingPageContentLayoutCollection
    {
        return $this->landingPageContentLayouts;
    }

    public function setLandingPageContentLayouts(LandingPageContentLayoutCollection $landingPageContentLayouts): void
    {
        $this->landingPageContentLayouts = $landingPageContentLayouts;
    }
}
