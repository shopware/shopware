<?php declare(strict_types=1);

namespace Shopware\Shop\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;
use Shopware\Shop\Event\ShopFormTranslationWrittenEvent;

class ShopFormTranslationWriteResource extends WriteResource
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
        $this->fields['shopForm'] = new ReferenceField('shopFormUuid', 'uuid', ShopFormWriteResource::class);
        $this->primaryKeyFields['shopFormUuid'] = (new FkField('shop_form_uuid', ShopFormWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['language'] = new ReferenceField('languageUuid', 'uuid', ShopWriteResource::class);
        $this->primaryKeyFields['languageUuid'] = (new FkField('language_uuid', ShopWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            ShopFormWriteResource::class,
            ShopWriteResource::class,
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ShopFormTranslationWrittenEvent
    {
        $event = new ShopFormTranslationWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        /**
         * @var WriteResource
         * @var string[]      $identifiers
         */
        foreach ($updates as $class => $identifiers) {
            $event->addEvent($class::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
