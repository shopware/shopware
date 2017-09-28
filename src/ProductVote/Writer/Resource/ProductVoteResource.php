<?php declare(strict_types=1);

namespace Shopware\ProductVote\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class ProductVoteResource extends Resource
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
        $this->fields['product'] = new ReferenceField('productUuid', 'uuid', \Shopware\Product\Writer\Resource\ProductResource::class);
        $this->fields['productUuid'] = (new FkField('product_uuid', \Shopware\Product\Writer\Resource\ProductResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['shop'] = new ReferenceField('shopUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopResource::class);
        $this->fields['shopUuid'] = (new FkField('shop_uuid', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid'));
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Writer\Resource\ProductResource::class,
            \Shopware\Shop\Writer\Resource\ShopResource::class,
            \Shopware\ProductVote\Writer\Resource\ProductVoteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\ProductVote\Event\ProductVoteWrittenEvent
    {
        $event = new \Shopware\ProductVote\Event\ProductVoteWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\ProductVote\Writer\Resource\ProductVoteResource::class])) {
            $event->addEvent(\Shopware\ProductVote\Writer\Resource\ProductVoteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
