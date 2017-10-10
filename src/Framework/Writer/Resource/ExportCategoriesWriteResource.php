<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\ExportCategoriesWrittenEvent;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class ExportCategoriesWriteResource extends WriteResource
{
    protected const FEEDID_FIELD = 'feedID';
    protected const CATEGORYID_FIELD = 'categoryID';

    public function __construct()
    {
        parent::__construct('s_export_categories');

        $this->primaryKeyFields[self::FEEDID_FIELD] = (new IntField('feedID'))->setFlags(new Required());
        $this->primaryKeyFields[self::CATEGORYID_FIELD] = (new IntField('categoryID'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ExportCategoriesWrittenEvent
    {
        $event = new ExportCategoriesWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
