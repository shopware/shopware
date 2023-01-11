<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Config\CmsAware;

use Shopware\Core\System\CustomEntity\Xml\Field\Field;
use Shopware\Core\System\CustomEntity\Xml\Field\JsonField;
use Shopware\Core\System\CustomEntity\Xml\Field\ManyToManyField;
use Shopware\Core\System\CustomEntity\Xml\Field\ManyToOneField;
use Shopware\Core\System\CustomEntity\Xml\Field\StringField;
use Shopware\Core\System\CustomEntity\Xml\Field\TextField;

/**
 * @package content
 *
 * @internal
 */
class CmsAwareEnrichment
{
    /**
     * @return Field[]
     */
    public static function getCmsAwareFields(): array
    {
        return [
            new StringField(['name' => 'sw_title', 'storeApiAware' => true, 'required' => false, 'translatable' => true]),
            new TextField(['name' => 'sw_content', 'storeApiAware' => true, 'required' => false, 'translatable' => true]),
            new ManyToOneField(['name' => 'sw_cms_page', 'reference' => 'cms_page', 'storeApiAware' => true, 'required' => false, 'onDelete' => 'set-null']),
            new JsonField(['name' => 'sw_slot_config', 'storeApiAware' => true, 'required' => false]),
            new ManyToManyField(['name' => 'sw_categories', 'reference' => 'category', 'storeApiAware' => true, 'required' => false, 'onDelete' => 'set-null']),
            new ManyToOneField(['name' => 'sw_media', 'reference' => 'media', 'storeApiAware' => true, 'required' => false, 'onDelete' => 'set-null']),

            // SEO fields
            new StringField(['name' => 'sw_seo_meta_title', 'storeApiAware' => true, 'required' => false, 'translatable' => true]),
            new StringField(['name' => 'sw_seo_meta_description', 'storeApiAware' => true, 'required' => false, 'translatable' => true]),
            new StringField(['name' => 'sw_seo_keywords', 'storeApiAware' => true, 'required' => false, 'translatable' => true]),
        ];
    }
}
