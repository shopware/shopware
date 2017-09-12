<?php declare(strict_types=1);

namespace Shopware\Shop\Writer;

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

class ShopFormResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const NAME_FIELD = 'name';
    protected const TEXT_FIELD = 'text';
    protected const EMAIL_FIELD = 'email';
    protected const EMAIL_TEMPLATE_FIELD = 'emailTemplate';
    protected const EMAIL_SUBJECT_FIELD = 'emailSubject';
    protected const TEXT2_FIELD = 'text2';
    protected const META_TITLE_FIELD = 'metaTitle';
    protected const META_KEYWORDS_FIELD = 'metaKeywords';
    protected const META_DESCRIPTION_FIELD = 'metaDescription';
    protected const TICKET_TYPE_ID_FIELD = 'ticketTypeId';
    protected const ISOCODE_FIELD = 'isocode';
    protected const SHOP_IDS_FIELD = 'shopIds';
    protected const SHOP_UUIDS_FIELD = 'shopUuids';

    public function __construct()
    {
        parent::__construct('shop_form');
        
        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::TEXT_FIELD] = (new LongTextField('text'))->setFlags(new Required());
        $this->fields[self::EMAIL_FIELD] = (new StringField('email'))->setFlags(new Required());
        $this->fields[self::EMAIL_TEMPLATE_FIELD] = (new LongTextField('email_template'))->setFlags(new Required());
        $this->fields[self::EMAIL_SUBJECT_FIELD] = (new StringField('email_subject'))->setFlags(new Required());
        $this->fields[self::TEXT2_FIELD] = (new LongTextField('text2'))->setFlags(new Required());
        $this->fields[self::META_TITLE_FIELD] = new StringField('meta_title');
        $this->fields[self::META_KEYWORDS_FIELD] = new StringField('meta_keywords');
        $this->fields[self::META_DESCRIPTION_FIELD] = new LongTextField('meta_description');
        $this->fields[self::TICKET_TYPE_ID_FIELD] = (new IntField('ticket_type_id'))->setFlags(new Required());
        $this->fields[self::ISOCODE_FIELD] = new StringField('isocode');
        $this->fields[self::SHOP_IDS_FIELD] = new StringField('shop_ids');
        $this->fields[self::SHOP_UUIDS_FIELD] = new LongTextField('shop_uuids');
        $this->fields['fields'] = new SubresourceField(\Shopware\Shop\Writer\ShopFormFieldResource::class);
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Shop\Writer\ShopFormResource::class,
            \Shopware\Shop\Writer\ShopFormFieldResource::class
        ];
    }
}
