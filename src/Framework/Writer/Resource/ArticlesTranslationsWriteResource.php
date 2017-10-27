<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Field\LongTextField;
use Shopware\Api\Write\Field\LongTextWithHtmlField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\ArticlesTranslationsWrittenEvent;

class ArticlesTranslationsWriteResource extends WriteResource
{
    protected const ARTICLEID_FIELD = 'articleID';
    protected const LANGUAGEID_FIELD = 'languageID';
    protected const NAME_FIELD = 'name';
    protected const KEYWORDS_FIELD = 'keywords';
    protected const DESCRIPTION_FIELD = 'description';
    protected const DESCRIPTION_LONG_FIELD = 'descriptionLong';
    protected const DESCRIPTION_CLEAR_FIELD = 'descriptionClear';

    public function __construct()
    {
        parent::__construct('s_articles_translations');

        $this->fields[self::ARTICLEID_FIELD] = (new IntField('articleID'))->setFlags(new Required());
        $this->fields[self::LANGUAGEID_FIELD] = (new IntField('languageID'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::KEYWORDS_FIELD] = (new LongTextField('keywords'))->setFlags(new Required());
        $this->fields[self::DESCRIPTION_FIELD] = (new LongTextField('description'))->setFlags(new Required());
        $this->fields[self::DESCRIPTION_LONG_FIELD] = (new LongTextWithHtmlField('description_long'))->setFlags(new Required());
        $this->fields[self::DESCRIPTION_CLEAR_FIELD] = (new LongTextField('description_clear'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ArticlesTranslationsWrittenEvent
    {
        $event = new ArticlesTranslationsWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

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
