<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\EmarketingBannersWrittenEvent;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class EmarketingBannersWriteResource extends WriteResource
{
    protected const DESCRIPTION_FIELD = 'description';
    protected const VALID_FROM_FIELD = 'validFrom';
    protected const VALID_TO_FIELD = 'validTo';
    protected const IMG_FIELD = 'img';
    protected const LINK_FIELD = 'link';
    protected const LINK_TARGET_FIELD = 'linkTarget';
    protected const CATEGORYID_FIELD = 'categoryID';
    protected const EXTENSION_FIELD = 'extension';

    public function __construct()
    {
        parent::__construct('s_emarketing_banners');

        $this->fields[self::DESCRIPTION_FIELD] = (new StringField('description'))->setFlags(new Required());
        $this->fields[self::VALID_FROM_FIELD] = new DateField('valid_from');
        $this->fields[self::VALID_TO_FIELD] = new DateField('valid_to');
        $this->fields[self::IMG_FIELD] = (new StringField('img'))->setFlags(new Required());
        $this->fields[self::LINK_FIELD] = (new StringField('link'))->setFlags(new Required());
        $this->fields[self::LINK_TARGET_FIELD] = (new StringField('link_target'))->setFlags(new Required());
        $this->fields[self::CATEGORYID_FIELD] = new IntField('categoryID');
        $this->fields[self::EXTENSION_FIELD] = (new StringField('extension'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): EmarketingBannersWrittenEvent
    {
        $event = new EmarketingBannersWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

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
