<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class BlogCommentResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const NAME_FIELD = 'name';
    protected const HEADLINE_FIELD = 'headline';
    protected const COMMENT_FIELD = 'comment';
    protected const CREATED_AT_FIELD = 'createdAt';
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
        $this->fields[self::CREATED_AT_FIELD] = (new DateField('created_at'))->setFlags(new Required());
        $this->fields[self::ACTIVE_FIELD] = (new BoolField('active'))->setFlags(new Required());
        $this->fields[self::EMAIL_FIELD] = (new StringField('email'))->setFlags(new Required());
        $this->fields[self::POINTS_FIELD] = (new FloatField('points'))->setFlags(new Required());
        $this->fields['blog'] = new ReferenceField('blogUuid', 'uuid', \Shopware\Framework\Write\Resource\BlogResource::class);
        $this->fields['blogUuid'] = new FkField('blog_uuid', \Shopware\Framework\Write\Resource\BlogResource::class, 'uuid');
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\BlogResource::class,
            \Shopware\Framework\Write\Resource\BlogCommentResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, array $errors = []): \Shopware\Framework\Event\BlogCommentWrittenEvent
    {
        $event = new \Shopware\Framework\Event\BlogCommentWrittenEvent($updates[self::class] ?? [], $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\BlogResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\BlogResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Framework\Write\Resource\BlogCommentResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\BlogCommentResource::createWrittenEvent($updates));
        }

        return $event;
    }

    public function getDefaults(string $type): array
    {
        if (self::FOR_UPDATE === $type) {
            return [
                self::UPDATED_AT_FIELD => new \DateTime(),
            ];
        }

        if (self::FOR_INSERT === $type) {
            return [
                self::UPDATED_AT_FIELD => new \DateTime(),
                self::CREATED_AT_FIELD => new \DateTime(),
            ];
        }

        throw new \InvalidArgumentException('Unable to generate default values, wrong type submitted');
    }
}
