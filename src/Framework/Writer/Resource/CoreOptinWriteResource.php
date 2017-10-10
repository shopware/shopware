<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\CoreOptinWrittenEvent;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class CoreOptinWriteResource extends WriteResource
{
    protected const TYPE_FIELD = 'type';
    protected const DATUM_FIELD = 'datum';
    protected const HASH_FIELD = 'hash';
    protected const DATA_FIELD = 'data';

    public function __construct()
    {
        parent::__construct('s_core_optin');

        $this->fields[self::TYPE_FIELD] = new StringField('type');
        $this->fields[self::DATUM_FIELD] = (new DateField('datum'))->setFlags(new Required());
        $this->fields[self::HASH_FIELD] = (new StringField('hash'))->setFlags(new Required());
        $this->fields[self::DATA_FIELD] = (new LongTextField('data'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): CoreOptinWrittenEvent
    {
        $event = new CoreOptinWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
