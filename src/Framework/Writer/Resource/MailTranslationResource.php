<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class MailTranslationResource extends Resource
{
    protected const FROM_MAIL_FIELD = 'fromMail';
    protected const FROM_NAME_FIELD = 'fromName';
    protected const SUBJECT_FIELD = 'subject';
    protected const CONTENT_FIELD = 'content';
    protected const CONTENT_HTML_FIELD = 'contentHtml';

    public function __construct()
    {
        parent::__construct('mail_translation');

        $this->fields[self::FROM_MAIL_FIELD] = (new StringField('from_mail'))->setFlags(new Required());
        $this->fields[self::FROM_NAME_FIELD] = (new StringField('from_name'))->setFlags(new Required());
        $this->fields[self::SUBJECT_FIELD] = (new StringField('subject'))->setFlags(new Required());
        $this->fields[self::CONTENT_FIELD] = (new LongTextField('content'))->setFlags(new Required());
        $this->fields[self::CONTENT_HTML_FIELD] = (new LongTextField('content_html'))->setFlags(new Required());
        $this->fields['mail'] = new ReferenceField('mailUuid', 'uuid', \Shopware\Framework\Write\Resource\MailResource::class);
        $this->primaryKeyFields['mailUuid'] = (new FkField('mail_uuid', \Shopware\Framework\Write\Resource\MailResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['language'] = new ReferenceField('languageUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopResource::class);
        $this->primaryKeyFields['languageUuid'] = (new FkField('language_uuid', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\MailResource::class,
            \Shopware\Shop\Writer\Resource\ShopResource::class,
            \Shopware\Framework\Write\Resource\MailTranslationResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Framework\Event\MailTranslationWrittenEvent
    {
        $event = new \Shopware\Framework\Event\MailTranslationWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\MailResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\MailResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Framework\Write\Resource\MailTranslationResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\MailTranslationResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
