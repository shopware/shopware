<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\EmarketingLastarticlesWrittenEvent;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class EmarketingLastarticlesWriteResource extends WriteResource
{
    protected const ARTICLEID_FIELD = 'articleID';
    protected const SESSIONID_FIELD = 'sessionID';
    protected const TIME_FIELD = 'time';
    protected const USERID_FIELD = 'userID';
    protected const SHOPID_FIELD = 'shopID';

    public function __construct()
    {
        parent::__construct('s_emarketing_lastarticles');

        $this->fields[self::ARTICLEID_FIELD] = (new IntField('articleID'))->setFlags(new Required());
        $this->fields[self::SESSIONID_FIELD] = new StringField('sessionID');
        $this->fields[self::TIME_FIELD] = new DateField('time');
        $this->fields[self::USERID_FIELD] = (new IntField('userID'))->setFlags(new Required());
        $this->fields[self::SHOPID_FIELD] = (new IntField('shopID'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): EmarketingLastarticlesWrittenEvent
    {
        $event = new EmarketingLastarticlesWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
