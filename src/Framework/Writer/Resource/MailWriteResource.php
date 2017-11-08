<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Api\Write\Field\BoolField;
use Shopware\Api\Write\Field\FkField;
use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Field\LongTextField;
use Shopware\Api\Write\Field\ReferenceField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Field\SubresourceField;
use Shopware\Api\Write\Field\TranslatedField;
use Shopware\Api\Write\Field\UuidField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\MailWrittenEvent;
use Shopware\OrderState\Writer\Resource\OrderStateWriteResource;
use Shopware\Shop\Writer\Resource\ShopWriteResource;

class MailWriteResource extends WriteResource
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
        $this->fields['orderState'] = new ReferenceField('orderStateUuid', 'uuid', OrderStateWriteResource::class);
        $this->fields['orderStateUuid'] = (new FkField('order_state_uuid', OrderStateWriteResource::class, 'uuid'));
        $this->fields[self::FROM_MAIL_FIELD] = new TranslatedField('fromMail', ShopWriteResource::class, 'uuid');
        $this->fields[self::FROM_NAME_FIELD] = new TranslatedField('fromName', ShopWriteResource::class, 'uuid');
        $this->fields[self::SUBJECT_FIELD] = new TranslatedField('subject', ShopWriteResource::class, 'uuid');
        $this->fields[self::CONTENT_FIELD] = new TranslatedField('content', ShopWriteResource::class, 'uuid');
        $this->fields[self::CONTENT_HTML_FIELD] = new TranslatedField('contentHtml', ShopWriteResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(MailTranslationWriteResource::class, 'languageUuid'))->setFlags(new Required());
        $this->fields['attachments'] = new SubresourceField(MailAttachmentWriteResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            OrderStateWriteResource::class,
            self::class,
            MailTranslationWriteResource::class,
            MailAttachmentWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): MailWrittenEvent
    {
        $uuids = [];
        if (isset($updates[self::class])) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new MailWrittenEvent($uuids, $context, $rawData, $errors);

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
