<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Api\Write\Field\DateField;
use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\EmarketingBannersStatisticsWrittenEvent;

class EmarketingBannersStatisticsWriteResource extends WriteResource
{
    protected const BANNERID_FIELD = 'bannerID';
    protected const DISPLAY_DATE_FIELD = 'displayDate';
    protected const CLICKS_FIELD = 'clicks';
    protected const VIEWS_FIELD = 'views';

    public function __construct()
    {
        parent::__construct('s_emarketing_banners_statistics');

        $this->fields[self::BANNERID_FIELD] = (new IntField('bannerID'))->setFlags(new Required());
        $this->fields[self::DISPLAY_DATE_FIELD] = (new DateField('display_date'))->setFlags(new Required());
        $this->fields[self::CLICKS_FIELD] = (new IntField('clicks'))->setFlags(new Required());
        $this->fields[self::VIEWS_FIELD] = (new IntField('views'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): EmarketingBannersStatisticsWrittenEvent
    {
        $event = new EmarketingBannersStatisticsWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

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
