<?php declare(strict_types=1);

namespace Shopware\Shop\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class ShopFormTranslationResource extends Resource
{
    protected const NAME_FIELD = 'name';
    protected const TEXT_FIELD = 'text';
    protected const EMAIL_FIELD = 'email';
    protected const EMAIL_TEMPLATE_FIELD = 'emailTemplate';
    protected const EMAIL_SUBJECT_FIELD = 'emailSubject';
    protected const TEXT2_FIELD = 'text2';
    protected const META_TITLE_FIELD = 'metaTitle';
    protected const META_KEYWORDS_FIELD = 'metaKeywords';
    protected const META_DESCRIPTION_FIELD = 'metaDescription';

    public function __construct()
    {
        parent::__construct('shop_form_translation');

        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::TEXT_FIELD] = (new LongTextField('text'))->setFlags(new Required());
        $this->fields[self::EMAIL_FIELD] = (new StringField('email'))->setFlags(new Required());
        $this->fields[self::EMAIL_TEMPLATE_FIELD] = (new LongTextField('email_template'))->setFlags(new Required());
        $this->fields[self::EMAIL_SUBJECT_FIELD] = (new StringField('email_subject'))->setFlags(new Required());
        $this->fields[self::TEXT2_FIELD] = (new LongTextField('text2'))->setFlags(new Required());
        $this->fields[self::META_TITLE_FIELD] = new StringField('meta_title');
        $this->fields[self::META_KEYWORDS_FIELD] = new StringField('meta_keywords');
        $this->fields[self::META_DESCRIPTION_FIELD] = new LongTextField('meta_description');
        $this->fields['shopForm'] = new ReferenceField('shopFormUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopFormResource::class);
        $this->primaryKeyFields['shopFormUuid'] = (new FkField('shop_form_uuid', \Shopware\Shop\Writer\Resource\ShopFormResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['language'] = new ReferenceField('languageUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopResource::class);
        $this->primaryKeyFields['languageUuid'] = (new FkField('language_uuid', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Shop\Writer\Resource\ShopFormResource::class,
            \Shopware\Shop\Writer\Resource\ShopResource::class,
            \Shopware\Shop\Writer\Resource\ShopFormTranslationResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Shop\Event\ShopFormTranslationWrittenEvent
    {
        $event = new \Shopware\Shop\Event\ShopFormTranslationWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopFormResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopFormResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopFormTranslationResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopFormTranslationResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
