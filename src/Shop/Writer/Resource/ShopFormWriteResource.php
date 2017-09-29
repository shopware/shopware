<?php declare(strict_types=1);

namespace Shopware\Shop\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class ShopFormWriteResource extends WriteResource
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
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid');
        $this->fields[self::TEXT_FIELD] = new TranslatedField('text', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid');
        $this->fields[self::EMAIL_FIELD] = new TranslatedField('email', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid');
        $this->fields[self::EMAIL_TEMPLATE_FIELD] = new TranslatedField('emailTemplate', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid');
        $this->fields[self::EMAIL_SUBJECT_FIELD] = new TranslatedField('emailSubject', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid');
        $this->fields[self::TEXT2_FIELD] = new TranslatedField('text2', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid');
        $this->fields[self::META_TITLE_FIELD] = new TranslatedField('metaTitle', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid');
        $this->fields[self::META_KEYWORDS_FIELD] = new TranslatedField('metaKeywords', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid');
        $this->fields[self::META_DESCRIPTION_FIELD] = new TranslatedField('metaDescription', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(\Shopware\Shop\Writer\Resource\ShopFormTranslationWriteResource::class, 'languageUuid'))->setFlags(new Required());
        $this->fields['fields'] = new SubresourceField(\Shopware\Shop\Writer\Resource\ShopFormFieldWriteResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Shop\Writer\Resource\ShopFormWriteResource::class,
            \Shopware\Shop\Writer\Resource\ShopFormTranslationWriteResource::class,
            \Shopware\Shop\Writer\Resource\ShopFormFieldWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Shop\Event\ShopFormWrittenEvent
    {
        $event = new \Shopware\Shop\Event\ShopFormWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopFormWriteResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopFormWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopFormTranslationWriteResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopFormTranslationWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopFormFieldWriteResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopFormFieldWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
