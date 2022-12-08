<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SeoUrlTemplate;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @package sales-channel
 *
 * @extends EntityCollection<SeoUrlTemplateEntity>
 */
class SeoUrlTemplateCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'seo_url_template_collection';
    }

    protected function getExpectedClass(): string
    {
        return SeoUrlTemplateEntity::class;
    }
}
