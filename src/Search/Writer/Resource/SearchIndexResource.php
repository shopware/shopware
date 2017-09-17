<?php declare(strict_types=1);

namespace Shopware\Search\Writer\Resource;

use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class SearchIndexResource extends Resource
{
    protected const KEYWORDID_FIELD = 'keywordID';
    protected const FIELDID_FIELD = 'fieldID';
    protected const ELEMENTID_FIELD = 'elementID';

    public function __construct()
    {
        parent::__construct('s_search_index');

        $this->primaryKeyFields[self::KEYWORDID_FIELD] = (new IntField('keywordID'))->setFlags(new Required());
        $this->primaryKeyFields[self::FIELDID_FIELD] = (new IntField('fieldID'))->setFlags(new Required());
        $this->primaryKeyFields[self::ELEMENTID_FIELD] = (new IntField('elementID'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Search\Writer\Resource\SearchIndexResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, array $errors = []): \Shopware\Search\Event\SearchIndexWrittenEvent
    {
        $event = new \Shopware\Search\Event\SearchIndexWrittenEvent($updates[self::class] ?? [], $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Search\Writer\Resource\SearchIndexResource::class])) {
            $event->addEvent(\Shopware\Search\Writer\Resource\SearchIndexResource::createWrittenEvent($updates));
        }

        return $event;
    }
}
