<?php declare(strict_types=1);

namespace Shopware\ProductDetail\Writer;

use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\LongTextWithHtmlField;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Resource;

class ProductDetailTranslationResource extends Resource
{
    protected const PRODUCT_DETAIL_UUID_FIELD = 'productDetailUuid';
    protected const LANGUAGE_UUID_FIELD = 'languageUuid';
    protected const ADDITIONAL_TEXT_FIELD = 'additionalText';
    protected const PACK_UNIT_FIELD = 'packUnit';

    public function __construct()
    {
        parent::__construct('product_detail_translation');
        
        $this->primaryKeyFields[self::PRODUCT_DETAIL_UUID_FIELD] = (new StringField('product_detail_uuid'))->setFlags(new Required());
        $this->primaryKeyFields[self::LANGUAGE_UUID_FIELD] = (new StringField('language_uuid'))->setFlags(new Required());
        $this->fields[self::ADDITIONAL_TEXT_FIELD] = new StringField('additional_text');
        $this->fields[self::PACK_UNIT_FIELD] = new StringField('pack_unit');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\ProductDetail\Writer\ProductDetailTranslationResource::class
        ];
    }
}
