<?php declare(strict_types=1);

namespace Shopware\Locale\Writer;

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

class LocaleResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const LOCALE_FIELD = 'locale';
    protected const LANGUAGE_FIELD = 'language';
    protected const TERRITORY_FIELD = 'territory';

    public function __construct()
    {
        parent::__construct('locale');
        
        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::LOCALE_FIELD] = (new StringField('locale'))->setFlags(new Required());
        $this->fields[self::LANGUAGE_FIELD] = (new StringField('language'))->setFlags(new Required());
        $this->fields[self::TERRITORY_FIELD] = (new StringField('territory'))->setFlags(new Required());
        $this->fields['shops'] = new SubresourceField(\Shopware\Shop\Writer\ShopResource::class);
        $this->fields['users'] = new SubresourceField(\Shopware\Framework\Write\Resource\UserResource::class);
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Locale\Writer\LocaleResource::class,
            \Shopware\Shop\Writer\ShopResource::class,
            \Shopware\Framework\Write\Resource\UserResource::class
        ];
    }
}
