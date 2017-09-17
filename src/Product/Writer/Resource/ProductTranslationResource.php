<?php declare(strict_types=1);

namespace Shopware\Product\Writer\Resource;

use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\LongTextWithHtmlField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class ProductTranslationResource extends Resource
{
    protected const NAME_FIELD = 'name';
    protected const KEYWORDS_FIELD = 'keywords';
    protected const DESCRIPTION_FIELD = 'description';
    protected const DESCRIPTION_LONG_FIELD = 'descriptionLong';
    protected const META_TITLE_FIELD = 'metaTitle';

    public function __construct()
    {
        parent::__construct('product_translation');

        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::KEYWORDS_FIELD] = new LongTextField('keywords');
        $this->fields[self::DESCRIPTION_FIELD] = new LongTextField('description');
        $this->fields[self::DESCRIPTION_LONG_FIELD] = new LongTextWithHtmlField('description_long');
        $this->fields[self::META_TITLE_FIELD] = new StringField('meta_title');
        $this->fields['product'] = new ReferenceField('productUuid', 'uuid', \Shopware\Product\Writer\Resource\ProductResource::class);
        $this->primaryKeyFields['productUuid'] = (new FkField('product_uuid', \Shopware\Product\Writer\Resource\ProductResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['language'] = new ReferenceField('languageUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopResource::class);
        $this->primaryKeyFields['languageUuid'] = (new FkField('language_uuid', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Writer\Resource\ProductResource::class,
            \Shopware\Shop\Writer\Resource\ShopResource::class,
            \Shopware\Product\Writer\Resource\ProductTranslationResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, array $errors = []): \Shopware\Product\Event\ProductTranslationWrittenEvent
    {
        $event = new \Shopware\Product\Event\ProductTranslationWrittenEvent($updates[self::class] ?? [], $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductTranslationResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductTranslationResource::createWrittenEvent($updates));
        }

        return $event;
    }
}
