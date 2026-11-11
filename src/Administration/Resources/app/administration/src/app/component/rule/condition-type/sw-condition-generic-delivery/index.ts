import type { PropType } from 'vue';
import template from './sw-condition-generic-delivery.html.twig';
import './sw-condition-generic-delivery.scss';

type RuleCondition = EntitySchema.Entity<'rule_condition'>;
type AppliesTo = 'cart' | 'delivery';
type CreateCondition = (conditionData: Record<string, unknown>, parentId: string | null, position: number) => RuleCondition;
type MoveNode = (parentCondition: RuleCondition, condition: RuleCondition) => void;

/**
 * @private
 * @sw-package fundamentals@after-sales
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: {
        repositoryFactory: { from: 'repositoryFactory' },
        insertNodeIntoTree: { from: 'insertNodeIntoTree' },
        removeNodeFromTree: { from: 'removeNodeFromTree' },
        createCondition: { from: 'createCondition' },
        childAssociationField: { from: 'childAssociationField' },
        unwrapDeliveryCondition: { from: 'unwrapDeliveryCondition', default: null },
    },

    props: {
        condition: {
            type: Object as PropType<RuleCondition>,
            required: true,
        },

        parentCondition: {
            type: Object as PropType<RuleCondition | null>,
            required: false,
            default: null,
        },
    },

    computed: {
        allowAppliesTo(): boolean {
            return Shopware.Feature.isActive('V6_8_0_0');
        },

        appliesToOptions(): { value: AppliesTo; label: string }[] {
            return [
                {
                    value: 'cart',
                    label: this.$t('global.sw-condition.condition.deliveryCondition.cart'),
                },
                {
                    value: 'delivery',
                    label: this.$t('global.sw-condition.condition.deliveryCondition.delivery'),
                },
            ];
        },

        appliesTo: {
            get(): AppliesTo {
                return this.parentCondition?.type === 'deliveryContainer' ? 'delivery' : 'cart';
            },
            set(appliesTo: AppliesTo): void {
                if (appliesTo === this.appliesTo) {
                    return;
                }

                if (appliesTo === 'delivery') {
                    this.wrapCondition();

                    return;
                }

                (this.unwrapDeliveryCondition as (condition: RuleCondition) => void)(this.condition);
            },
        },
    },

    methods: {
        wrapCondition(): void {
            const parentCondition = this.parentCondition as RuleCondition;
            const childAssociationField = this.childAssociationField as string;
            const children = this.condition[
                childAssociationField as keyof RuleCondition
            ] as EntityCollection<'rule_condition'>;
            const child = Object.assign(
                this.repositoryFactory.create('rule_condition').create(),
                Object.fromEntries(Object.entries(this.condition).filter(([key]) => key !== 'id')),
            );

            (this.removeNodeFromTree as MoveNode)(parentCondition, this.condition);
            (this.insertNodeIntoTree as MoveNode)(
                parentCondition,
                (this.createCondition as CreateCondition)(
                    {
                        type: 'deliveryContainer',
                        value: {},
                        [childAssociationField]: new Shopware.Data.EntityCollection(
                            children.source,
                            children.entity,
                            children.context,
                            null,
                            [child],
                        ),
                    },
                    parentCondition.id,
                    this.condition.position ?? 0,
                ),
            );
        },
    },
});
