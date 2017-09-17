<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class ExportCategoriesResource extends Resource
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
            \Shopware\Framework\Write\Resource\ExportCategoriesResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, array $errors = []): \Shopware\Framework\Event\ExportCategoriesWrittenEvent
    {
        $event = new \Shopware\Framework\Event\ExportCategoriesWrittenEvent($updates[self::class] ?? [], $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\ExportCategoriesResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\ExportCategoriesResource::createWrittenEvent($updates));
        }

        return $event;
    }
}
