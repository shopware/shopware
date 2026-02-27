<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Entity;

use Shopware\Core\Content\Category\Aggregate\CategoryContentLayout\CategoryContentLayoutCollection;
use Shopware\Core\Content\LandingPage\Aggregate\LandingPageContentLayout\LandingPageContentLayoutCollection;
use Shopware\Core\Content\Product\Aggregate\ProductContentLayout\ProductContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
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
     * @var list<ContentElement>
     */
    protected array $layout;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $schema = null;

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
     * @return list<ContentElement>
     */
    public function getLayout(): array
    {
        return $this->layout;
    }

    /**
     * @param list<ContentElement> $layout
     */
    public function setLayout(array $layout): void
    {
        $this->layout = $layout;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getSchema(): ?array
    {
        return $this->schema;
    }

    /**
     * @param array<string, mixed>|null $schema
     */
    public function setSchema(?array $schema): void
    {
        $this->schema = $schema;
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
