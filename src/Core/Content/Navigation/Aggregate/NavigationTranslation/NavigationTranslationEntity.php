<?php declare(strict_types=1);

namespace Shopware\Core\Content\Navigation\Aggregate\NavigationTranslation;

use Shopware\Core\Content\Navigation\NavigationEntity;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;

class NavigationTranslationEntity extends TranslationEntity
{
    /**
     * @var string
     */
    protected $navigationId;

    /**
     * @var string|null
     */
    protected $name;

    /**
     * @var NavigationEntity|null
     */
    protected $navigation;

    public function getNavigationId(): string
    {
        return $this->navigationId;
    }

    public function setNavigationId(string $navigationId): void
    {
        $this->navigationId = $navigationId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getNavigation(): ?NavigationEntity
    {
        return $this->navigation;
    }

    public function setNavigation(NavigationEntity $navigation): void
    {
        $this->navigation = $navigation;
    }
}
