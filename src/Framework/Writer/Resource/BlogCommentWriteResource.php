<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\BlogCommentWrittenEvent;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class BlogCommentWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const NAME_FIELD = 'name';
    protected const HEADLINE_FIELD = 'headline';
    protected const COMMENT_FIELD = 'comment';
    protected const ACTIVE_FIELD = 'active';
    protected const EMAIL_FIELD = 'email';
    protected const POINTS_FIELD = 'points';

    public function __construct()
    {
        parent::__construct('blog_comment');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::HEADLINE_FIELD] = (new StringField('headline'))->setFlags(new Required());
        $this->fields[self::COMMENT_FIELD] = (new LongTextField('comment'))->setFlags(new Required());
        $this->fields[self::ACTIVE_FIELD] = (new BoolField('active'))->setFlags(new Required());
        $this->fields[self::EMAIL_FIELD] = (new StringField('email'))->setFlags(new Required());
        $this->fields[self::POINTS_FIELD] = (new FloatField('points'))->setFlags(new Required());
        $this->fields['blog'] = new ReferenceField('blogUuid', 'uuid', BlogWriteResource::class);
        $this->fields['blogUuid'] = (new FkField('blog_uuid', BlogWriteResource::class, 'uuid'));
    }

    public function getWriteOrder(): array
    {
        return [
            BlogWriteResource::class,
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): BlogCommentWrittenEvent
    {
        $event = new BlogCommentWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        if (!empty($updates[BlogWriteResource::class])) {
            $event->addEvent(BlogWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
