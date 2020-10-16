<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Event;

class NavigationRouteValidationEvent
{
    /**
     * @var array
     */
    private $ids;

    public function __construct(array $ids)
    {
        $this->ids = $ids;
    }

    public function getIds(): array
    {
        return $this->ids;
    }

    public function setIds(array $ids): void
    {
        $this->ids = $ids;
    }

    public function addId(string $id): void
    {
        $this->ids[] = $id;
        $this->ids = array_unique($this->ids);
    }
}
