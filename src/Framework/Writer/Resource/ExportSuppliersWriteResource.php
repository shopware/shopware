<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\ExportSuppliersWrittenEvent;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class ExportSuppliersWriteResource extends WriteResource
{
    protected const FEEDID_FIELD = 'feedID';
    protected const SUPPLIERID_FIELD = 'supplierID';

    public function __construct()
    {
        parent::__construct('s_export_suppliers');

        $this->primaryKeyFields[self::FEEDID_FIELD] = (new IntField('feedID'))->setFlags(new Required());
        $this->primaryKeyFields[self::SUPPLIERID_FIELD] = (new IntField('supplierID'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ExportSuppliersWrittenEvent
    {
        $event = new ExportSuppliersWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
