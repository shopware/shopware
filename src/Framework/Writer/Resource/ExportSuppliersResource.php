<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class ExportSuppliersResource extends Resource
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
            \Shopware\Framework\Write\Resource\ExportSuppliersResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Framework\Event\ExportSuppliersWrittenEvent
    {
        $event = new \Shopware\Framework\Event\ExportSuppliersWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\ExportSuppliersResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\ExportSuppliersResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
