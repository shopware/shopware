<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
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
            \Shopware\Framework\Write\Resource\ExportCategoriesWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Framework\Event\ExportCategoriesWrittenEvent
    {
        $event = new \Shopware\Framework\Event\ExportCategoriesWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\ExportCategoriesWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\ExportCategoriesWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
