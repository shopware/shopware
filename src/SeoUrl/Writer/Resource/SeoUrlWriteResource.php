<?php declare(strict_types=1);

namespace Shopware\SeoUrl\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;
use Shopware\SeoUrl\Event\SeoUrlWrittenEvent;

class SeoUrlWriteResource extends WriteResource
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
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): SeoUrlWrittenEvent
    {
        $event = new SeoUrlWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        /**
         * @var WriteResource
         * @var string[]      $identifiers
         */
        foreach ($updates as $class => $identifiers) {
            if (!array_key_exists($class, $updates) || count($updates[$class]) === 0) {
                continue;
            }

            $event->addEvent($class::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
