<?php declare(strict_types=1);

namespace Shopware\ProductVote\Writer\Resource;

use Shopware\Api\Write\Field\BoolField;
use Shopware\Api\Write\Field\DateField;
use Shopware\Api\Write\Field\FkField;
use Shopware\Api\Write\Field\FloatField;
use Shopware\Api\Write\Field\LongTextField;
use Shopware\Api\Write\Field\ReferenceField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Field\UuidField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Product\Writer\Resource\ProductWriteResource;
use Shopware\ProductVote\Event\ProductVoteWrittenEvent;
use Shopware\Shop\Writer\Resource\ShopWriteResource;

class ProductVoteWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const NAME_FIELD = 'name';
    protected const HEADLINE_FIELD = 'headline';
    protected const COMMENT_FIELD = 'comment';
    protected const POINTS_FIELD = 'points';
    protected const ACTIVE_FIELD = 'active';
    protected const EMAIL_FIELD = 'email';
    protected const ANSWER_FIELD = 'answer';
    protected const ANSWERED_AT_FIELD = 'answeredAt';

    public function __construct()
    {
        parent::__construct('product_vote');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::HEADLINE_FIELD] = (new StringField('headline'))->setFlags(new Required());
        $this->fields[self::COMMENT_FIELD] = (new LongTextField('comment'))->setFlags(new Required());
        $this->fields[self::POINTS_FIELD] = (new FloatField('points'))->setFlags(new Required());
        $this->fields[self::ACTIVE_FIELD] = new BoolField('active');
        $this->fields[self::EMAIL_FIELD] = (new StringField('email'))->setFlags(new Required());
        $this->fields[self::ANSWER_FIELD] = new LongTextField('answer');
        $this->fields[self::ANSWERED_AT_FIELD] = new DateField('answered_at');
        $this->fields['product'] = new ReferenceField('productUuid', 'uuid', ProductWriteResource::class);
        $this->fields['productUuid'] = (new FkField('product_uuid', ProductWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['shop'] = new ReferenceField('shopUuid', 'uuid', ShopWriteResource::class);
        $this->fields['shopUuid'] = (new FkField('shop_uuid', ShopWriteResource::class, 'uuid'));
    }

    public function getWriteOrder(): array
    {
        return [
            ProductWriteResource::class,
            ShopWriteResource::class,
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ProductVoteWrittenEvent
    {
        $uuids = [];
        if (isset($updates[self::class])) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new ProductVoteWrittenEvent($uuids, $context, $rawData, $errors);

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
