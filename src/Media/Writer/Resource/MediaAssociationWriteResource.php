<?php declare(strict_types=1);

namespace Shopware\Media\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;
use Shopware\Media\Event\MediaAssociationWrittenEvent;

class MediaAssociationWriteResource extends WriteResource
{
    protected const MEDIAID_FIELD = 'mediaID';
    protected const TARGETTYPE_FIELD = 'targetType';
    protected const TARGETID_FIELD = 'targetID';

    public function __construct()
    {
        parent::__construct('s_media_association');

        $this->fields[self::MEDIAID_FIELD] = (new IntField('mediaID'))->setFlags(new Required());
        $this->fields[self::TARGETTYPE_FIELD] = (new StringField('targetType'))->setFlags(new Required());
        $this->fields[self::TARGETID_FIELD] = (new IntField('targetID'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): MediaAssociationWrittenEvent
    {
        $event = new MediaAssociationWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
