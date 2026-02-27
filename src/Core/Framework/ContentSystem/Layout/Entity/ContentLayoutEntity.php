<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Entity;

use Shopware\Core\Framework\ContentSystem\Adapter\Entity\CategoryContentLayout\CategoryContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Adapter\Entity\FooterContentLayout\FooterContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Adapter\Entity\HeaderContentLayout\HeaderContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Adapter\Entity\LandingPageContentLayout\LandingPageContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Adapter\Entity\ProductContentLayout\ProductContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
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

    protected ?HeaderContentLayoutCollection $headerContentLayouts = null;

    protected ?FooterContentLayoutCollection $footerContentLayouts = null;

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

    public function getHeaderContentLayouts(): ?HeaderContentLayoutCollection
    {
        return $this->headerContentLayouts;
    }

    public function setHeaderContentLayouts(HeaderContentLayoutCollection $headerContentLayouts): void
    {
        $this->headerContentLayouts = $headerContentLayouts;
    }

    public function getFooterContentLayouts(): ?FooterContentLayoutCollection
    {
        return $this->footerContentLayouts;
    }

    public function setFooterContentLayouts(FooterContentLayoutCollection $footerContentLayouts): void
    {
        $this->footerContentLayouts = $footerContentLayouts;
    }
}
