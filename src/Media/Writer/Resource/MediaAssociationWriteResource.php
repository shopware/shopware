<?php declare(strict_types=1);

namespace Shopware\Media\Writer\Resource;

use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
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

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): MediaAssociationWrittenEvent
    {
        $event = new MediaAssociationWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

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
