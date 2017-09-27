<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class MailResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const NAME_FIELD = 'name';
    protected const FROM_MAIL_FIELD = 'fromMail';
    protected const FROM_NAME_FIELD = 'fromName';
    protected const SUBJECT_FIELD = 'subject';
    protected const CONTENT_FIELD = 'content';
    protected const CONTENT_HTML_FIELD = 'contentHtml';
    protected const IS_HTML_FIELD = 'isHtml';
    protected const ATTACHMENT_FIELD = 'attachment';
    protected const TYPE_FIELD = 'type';
    protected const CONTEXT_FIELD = 'context';
    protected const DIRTY_FIELD = 'dirty';

    public function __construct()
    {
        parent::__construct('mail');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::FROM_MAIL_FIELD] = (new StringField('from_mail'))->setFlags(new Required());
        $this->fields[self::FROM_NAME_FIELD] = (new StringField('from_name'))->setFlags(new Required());
        $this->fields[self::SUBJECT_FIELD] = (new StringField('subject'))->setFlags(new Required());
        $this->fields[self::CONTENT_FIELD] = (new LongTextField('content'))->setFlags(new Required());
        $this->fields[self::CONTENT_HTML_FIELD] = (new LongTextField('content_html'))->setFlags(new Required());
        $this->fields[self::IS_HTML_FIELD] = (new BoolField('is_html'))->setFlags(new Required());
        $this->fields[self::ATTACHMENT_FIELD] = (new StringField('attachment'))->setFlags(new Required());
        $this->fields[self::TYPE_FIELD] = new IntField('mail_type');
        $this->fields[self::CONTEXT_FIELD] = new LongTextField('context');
        $this->fields[self::DIRTY_FIELD] = new BoolField('dirty');
        $this->fields['orderState'] = new ReferenceField('orderStateUuid', 'uuid', \Shopware\OrderState\Writer\Resource\OrderStateResource::class);
        $this->fields['orderStateUuid'] = (new FkField('order_state_uuid', \Shopware\OrderState\Writer\Resource\OrderStateResource::class, 'uuid'));
        $this->fields[self::FROM_MAIL_FIELD] = new TranslatedField('fromMail', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid');
        $this->fields[self::FROM_NAME_FIELD] = new TranslatedField('fromName', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid');
        $this->fields[self::SUBJECT_FIELD] = new TranslatedField('subject', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid');
        $this->fields[self::CONTENT_FIELD] = new TranslatedField('content', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid');
        $this->fields[self::CONTENT_HTML_FIELD] = new TranslatedField('contentHtml', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(\Shopware\Framework\Write\Resource\MailTranslationResource::class, 'languageUuid'))->setFlags(new Required());
        $this->fields['attachments'] = new SubresourceField(\Shopware\Framework\Write\Resource\MailAttachmentResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\OrderState\Writer\Resource\OrderStateResource::class,
            \Shopware\Framework\Write\Resource\MailResource::class,
            \Shopware\Framework\Write\Resource\MailTranslationResource::class,
            \Shopware\Framework\Write\Resource\MailAttachmentResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Framework\Event\MailWrittenEvent
    {
        $event = new \Shopware\Framework\Event\MailWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\OrderState\Writer\Resource\OrderStateResource::class])) {
            $event->addEvent(\Shopware\OrderState\Writer\Resource\OrderStateResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Framework\Write\Resource\MailResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\MailResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Framework\Write\Resource\MailTranslationResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\MailTranslationResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Framework\Write\Resource\MailAttachmentResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\MailAttachmentResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
