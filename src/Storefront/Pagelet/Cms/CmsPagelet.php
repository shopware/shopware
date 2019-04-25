<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Cms;

use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Framework\Struct\Struct;

class CmsPagelet extends Struct
{
    /**
     * @var CmsPageEntity
     */
    private $entity;

    public function __construct(CmsPageEntity $entity)
    {
        $this->entity = $entity;
    }

    public function getCmsPage(): CmsPageEntity
    {
        return $this->entity;
    }
}
