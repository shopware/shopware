<?php declare(strict_types=1);

namespace Shopware\Media\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class MediaAssociationResource extends Resource
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
            \Shopware\Media\Writer\Resource\MediaAssociationResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Media\Event\MediaAssociationWrittenEvent
    {
        $event = new \Shopware\Media\Event\MediaAssociationWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Media\Writer\Resource\MediaAssociationResource::class])) {
            $event->addEvent(\Shopware\Media\Writer\Resource\MediaAssociationResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
