<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event\EventAction;

use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Event\EventAction\Aggregate\EventActionRule\EventActionRuleDefinition;
use Shopware\Core\Framework\Event\EventAction\Aggregate\EventActionSalesChannel\EventActionSalesChannelDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

class EventActionDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'event_action';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return EventActionCollection::class;
    }

    public function getEntityClass(): string
    {
        return EventActionEntity::class;
    }

    public function getDefaults(): array
    {
        $defaults = parent::getDefaults();

        return array_merge($defaults, ['active' => true]);
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        $fields = new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('event_name', 'eventName', 500))->addFlags(new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new StringField('action_name', 'actionName', 500))->addFlags(new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new JsonField('config', 'config'),
            new BoolField('active', 'active'),
            (new StringField('title', 'title', 500))->addFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new ManyToManyAssociationField('rules', RuleDefinition::class, EventActionRuleDefinition::class, 'event_action_id', 'rule_id'))->addFlags(new CascadeDelete(), new SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING)),
            (new ManyToManyAssociationField('salesChannels', SalesChannelDefinition::class, EventActionSalesChannelDefinition::class, 'event_action_id', 'sales_channel_id'))->addFlags(new CascadeDelete(), new SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING)),
            (new CustomFields())->addFlags(new ApiAware()),
        ]);

        return $fields;
    }
}
