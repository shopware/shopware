<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class MultiEditFilterResource extends Resource
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
            \Shopware\Framework\Write\Resource\MultiEditFilterResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): ?\Shopware\Framework\Event\MultiEditFilterWrittenEvent
    {
        if (empty($updates) || !array_key_exists(self::class, $updates)) {
            return null;
        }

        $event = new \Shopware\Framework\Event\MultiEditFilterWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        $event->addEvent(\Shopware\Framework\Write\Resource\MultiEditFilterResource::createWrittenEvent($updates, $context));

        return $event;
    }
}
