<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SeoUrlRoute;

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

    /**
     * @var string|null
     */
    private $error;

    public function __construct(Entity $entity, array $infoPathContext, array $seoPathInfoContext, ?string $error = null)
    {
        $this->entity = $entity;
        $this->infoPathContext = $infoPathContext;
        $this->seoPathInfoContext = $seoPathInfoContext;
        $this->error = $error;
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

    public function getError(): ?string
    {
        return $this->error;
    }
}
