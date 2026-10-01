<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SeoUrlRoute;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class SeoUrlMapping
{
    /**
     * @param array<string, mixed> $infoPathContext
     * @param array<string, mixed> $seoPathInfoContext
     */
    public function __construct(
        private readonly Entity $entity,
        private readonly array $infoPathContext,
        private readonly array $seoPathInfoContext,
        private readonly ?string $error = null
    ) {
    }

    public function getEntity(): Entity
    {
        return $this->entity;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSeoPathInfoContext(): array
    {
        return $this->seoPathInfoContext;
    }

    /**
     * @return array<string, mixed>
     */
    public function getInfoPathContext(): array
    {
        return $this->infoPathContext;
    }

    public function getError(): ?string
    {
        return $this->error;
    }
}
