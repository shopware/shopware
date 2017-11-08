<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Api\Write\Field\BoolField;
use Shopware\Api\Write\Field\DateField;
use Shopware\Api\Write\Field\LongTextField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\MultiEditFilterWrittenEvent;

class MultiEditFilterWriteResource extends WriteResource
{
    protected const NAME_FIELD = 'name';
    protected const FILTER_STRING_FIELD = 'filterString';
    protected const DESCRIPTION_FIELD = 'description';
    protected const CREATED_FIELD = 'created';
    protected const IS_FAVORITE_FIELD = 'isFavorite';
    protected const IS_SIMPLE_FIELD = 'isSimple';

    public function __construct()
    {
        parent::__construct('s_multi_edit_filter');

        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::FILTER_STRING_FIELD] = (new LongTextField('filter_string'))->setFlags(new Required());
        $this->fields[self::DESCRIPTION_FIELD] = (new LongTextField('description'))->setFlags(new Required());
        $this->fields[self::CREATED_FIELD] = new DateField('created');
        $this->fields[self::IS_FAVORITE_FIELD] = new BoolField('is_favorite');
        $this->fields[self::IS_SIMPLE_FIELD] = new BoolField('is_simple');
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): MultiEditFilterWrittenEvent
    {
        $uuids = [];
        if (isset($updates[self::class])) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new MultiEditFilterWrittenEvent($uuids, $context, $rawData, $errors);

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
