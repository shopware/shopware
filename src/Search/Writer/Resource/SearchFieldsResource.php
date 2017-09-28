<?php declare(strict_types=1);

namespace Shopware\Search\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class SearchFieldsResource extends Resource
{
    protected const NAME_FIELD = 'name';
    protected const RELEVANCE_FIELD = 'relevance';
    protected const FIELD_FIELD = 'field';
    protected const TABLEID_FIELD = 'tableID';

    public function __construct()
    {
        parent::__construct('s_search_fields');

        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::RELEVANCE_FIELD] = (new IntField('relevance'))->setFlags(new Required());
        $this->fields[self::FIELD_FIELD] = (new StringField('field'))->setFlags(new Required());
        $this->fields[self::TABLEID_FIELD] = (new IntField('tableID'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Search\Writer\Resource\SearchFieldsResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): ?\Shopware\Search\Event\SearchFieldsWrittenEvent
    {
        if (empty($updates) || !array_key_exists(self::class, $updates)) {
            return null;
        }

        $event = new \Shopware\Search\Event\SearchFieldsWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        $event->addEvent(\Shopware\Search\Writer\Resource\SearchFieldsResource::createWrittenEvent($updates, $context));

        return $event;
    }
}
