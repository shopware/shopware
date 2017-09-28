<?php declare(strict_types=1);

namespace Shopware\SeoUrl\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class SeoUrlResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const SEO_HASH_FIELD = 'seoHash';
    protected const SHOP_UUID_FIELD = 'shopUuid';
    protected const NAME_FIELD = 'name';
    protected const FOREIGN_KEY_FIELD = 'foreignKey';
    protected const PATH_INFO_FIELD = 'pathInfo';
    protected const SEO_PATH_INFO_FIELD = 'seoPathInfo';
    protected const IS_CANONICAL_FIELD = 'isCanonical';

    public function __construct()
    {
        parent::__construct('seo_url');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::SEO_HASH_FIELD] = (new StringField('seo_hash'))->setFlags(new Required());
        $this->fields[self::SHOP_UUID_FIELD] = (new StringField('shop_uuid'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::FOREIGN_KEY_FIELD] = (new StringField('foreign_key'))->setFlags(new Required());
        $this->fields[self::PATH_INFO_FIELD] = (new LongTextField('path_info'))->setFlags(new Required());
        $this->fields[self::SEO_PATH_INFO_FIELD] = (new LongTextField('seo_path_info'))->setFlags(new Required());
        $this->fields[self::IS_CANONICAL_FIELD] = new BoolField('is_canonical');
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\SeoUrl\Writer\Resource\SeoUrlResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): ?\Shopware\SeoUrl\Event\SeoUrlWrittenEvent
    {
        if (empty($updates) || !array_key_exists(self::class, $updates)) {
            return null;
        }

        $event = new \Shopware\SeoUrl\Event\SeoUrlWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        $event->addEvent(\Shopware\SeoUrl\Writer\Resource\SeoUrlResource::createWrittenEvent($updates, $context));

        return $event;
    }
}
