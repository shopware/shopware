<?php declare(strict_types=1);

namespace Shopware\Search\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class SearchKeywordsWriteResource extends WriteResource
{
    protected const KEYWORD_FIELD = 'keyword';
    protected const SOUNDEX_FIELD = 'soundex';

    public function __construct()
    {
        parent::__construct('s_search_keywords');

        $this->fields[self::KEYWORD_FIELD] = (new StringField('keyword'))->setFlags(new Required());
        $this->fields[self::SOUNDEX_FIELD] = new StringField('soundex');
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Search\Writer\Resource\SearchKeywordsWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Search\Event\SearchKeywordsWrittenEvent
    {
        $event = new \Shopware\Search\Event\SearchKeywordsWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Search\Writer\Resource\SearchKeywordsWriteResource::class])) {
            $event->addEvent(\Shopware\Search\Writer\Resource\SearchKeywordsWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
