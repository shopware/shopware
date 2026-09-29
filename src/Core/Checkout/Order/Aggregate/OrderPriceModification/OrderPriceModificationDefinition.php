<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderPriceModification;

use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TaxRuleCollectionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * A single, individually addressable order-level price modification (a reduction or a surcharge) —
 * the persisted counterpart to a PriceModifier. A plugin can contribute rows via
 * AbstractOrderAwarePriceProcessor, and an admin can independently add, edit, or delete rows on a
 * placed order.
 */
#[Package('checkout')]
class OrderPriceModificationDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'order_price_modification';

    /**
     * `price_definition.type` discriminator values, matching AbsolutePriceDefinition/
     * PercentagePriceDefinition::getType() directly.
     */
    final public const PRICE_DEFINITION_TYPE_ABSOLUTE = 'absolute';

    final public const PRICE_DEFINITION_TYPE_PERCENTAGE = 'percentage';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return OrderPriceModificationCollection::class;
    }

    public function getEntityClass(): string
    {
        return OrderPriceModificationEntity::class;
    }

    public function since(): ?string
    {
        return '6.7.15.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return OrderDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            (new VersionField())->addFlags(new ApiAware()),

            (new FkField('order_id', 'orderId', OrderDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new ReferenceVersionField(OrderDefinition::class))->addFlags(new ApiAware(), new Required()),

            (new StringField('label', 'label'))->addFlags(new ApiAware(), new Required())->setDescription('Human-readable label shown in the storefront/administration/invoice, e.g. "Merchant reduction" or "Rush order fee".'),
            (new LongTextField('description', 'description'))->addFlags(new ApiAware())->setDescription('Free-text elaboration beyond the label, e.g. why a surcharge was applied. Informational only.'),
            (new FloatField('price', 'price'))->addFlags(new ApiAware(), new Required())->setDescription('Signed: negative reduces the order total, positive surcharges it. For a "percentage" price_definition, this is a cache of its last computed value; price_definition is the authority on recalculation.'),
            (new JsonField('price_definition', 'priceDefinition'))->addFlags(new ApiAware())->setDescription('How `price` was (or should be re-) computed, shaped like Shopware\'s PriceDefinitionInterface JSON — {"type": "absolute", "price": ..., "filter": null} or {"type": "percentage", "filter": null, "percentage": ..., "target": {"lineItems": bool, "shipping": bool}}. NULL for a manually added row or a legacy row predating this field.'),
            (new IntField('position', 'position'))->addFlags(new ApiAware())->setDescription('Application order among rows with the same tax treatment; all taxable rows are applied before any tax-exempt row regardless of position.'),
            (new StringField('type', 'type'))->addFlags(new ApiAware())->setDescription('Technical name of the plugin that contributed this row, or NULL if added manually in the Administration.'),
            (new StringField('referenced_id', 'referencedId'))->addFlags(new ApiAware())->setDescription('Stable per-type identifier (e.g. "merchant-reduction"), or NULL for a manually added row.'),
            (new JsonField('payload', 'payload'))->addFlags(new ApiAware())->setDescription('Free-form data the contributing plugin (see `type`) stores alongside the row, e.g. a redeemed voucher code. Carried over from the cart unchanged; not interpreted by Shopware.'),
            (new TaxRuleCollectionField('tax_rules', 'taxRules'))->addFlags(new ApiAware())->setDescription('NULL means tax-exempt: the total is adjusted but tax is calculated on the original value. An empty collection applies proportionally across every tax rate; a non-empty collection restricts it to the listed rate(s).'),
            (new CustomFields())->addFlags(new ApiAware()),

            new ManyToOneAssociationField('order', 'order_id', OrderDefinition::class, 'id', false),
        ]);
    }
}
