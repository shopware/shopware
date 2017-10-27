<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\CoreRewriteUrlsWrittenEvent;

class CoreRewriteUrlsWriteResource extends WriteResource
{
    protected const ORG_PATH_FIELD = 'orgPath';
    protected const PATH_FIELD = 'path';
    protected const MAIN_FIELD = 'main';
    protected const SUBSHOPID_FIELD = 'subshopID';

    public function __construct()
    {
        parent::__construct('s_core_rewrite_urls');

        $this->fields[self::ORG_PATH_FIELD] = (new StringField('org_path'))->setFlags(new Required());
        $this->fields[self::PATH_FIELD] = (new StringField('path'))->setFlags(new Required());
        $this->fields[self::MAIN_FIELD] = (new IntField('main'))->setFlags(new Required());
        $this->fields[self::SUBSHOPID_FIELD] = (new IntField('subshopID'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): CoreRewriteUrlsWrittenEvent
    {
        $event = new CoreRewriteUrlsWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

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
