<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Seo\SeoUrlRoute;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;

class SeoUrlMapping
{
    /**
     * @var Entity
     */
    private $entity;

    /**
     * @var array
     */
    private $infoPathContext;

    /**
     * @var array
     */
    private $seoPathInfoContext;

    public function __construct(Entity $entity, array $infoPathContext, array $seoPathInfoContext)
    {
        $this->entity = $entity;
        $this->infoPathContext = $infoPathContext;
        $this->seoPathInfoContext = $seoPathInfoContext;
    }

    public function getEntity(): Entity
    {
        return $this->entity;
    }

    public function getSeoPathInfoContext(): array
    {
        return $this->seoPathInfoContext;
    }

    public function getInfoPathContext(): array
    {
        return $this->infoPathContext;
    }
}
