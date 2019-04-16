<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\SeoUrl;

class CanonicalUrlCollection extends SeoUrlCollection
{
    public function __construct(iterable $elements = [])
    {
        parent::__construct($elements);
    }

    /**
     * @param SeoUrlEntity $entity
     */
    public function add($entity): void
    {
        // index by foreign key so that each entity has one canonical url
        $this->set($entity->getForeignKey(), $entity);
    }
}
