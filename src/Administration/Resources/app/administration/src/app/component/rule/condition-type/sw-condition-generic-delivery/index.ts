import type { PropType } from 'vue';
import template from './sw-condition-generic-delivery.html.twig';
import './sw-condition-generic-delivery.scss';

type RuleCondition = EntitySchema.Entity<'rule_condition'>;
type AppliesTo = 'cart' | 'delivery';
type CreateCondition = (conditionData: Record<string, unknown>, parentId: string | null, position: number) => RuleCondition;
type MoveNode = (parentCondition: RuleCondition, condition: RuleCondition) => void;
type RestrictedAssignment = { associationName: string; snippet: string };
type AppliesToOption = { value: AppliesTo; label: string; disabled: boolean };

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
        restrictedConditions: { from: 'restrictedConditions', default: () => ({}) },
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

        deliveryRestrictions(): RestrictedAssignment[] {
            return (this.restrictedConditions as Record<string, RestrictedAssignment[]>).deliveryContainer ?? [];
        },

        appliesToOptions(): AppliesToOption[] {
            return [
                {
                    value: 'cart',
                    label: this.$t('global.sw-condition.condition.deliveryCondition.cart'),
                    disabled: false,
                },
                {
                    value: 'delivery',
                    label: this.$t('global.sw-condition.condition.deliveryCondition.delivery'),
                    disabled: this.deliveryRestrictions.length > 0,
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
        getOptionTooltip(option: AppliesToOption): { message: string; disabled: boolean; width?: number } {
            if (!option.disabled) {
                return { message: '', disabled: true };
            }

            const assignments = this.deliveryRestrictions
                .map(({ associationName, snippet }) => {
                    const relation = /promotion/i.test(associationName) ? 'promotion' : associationName;

                    return this.$t(`sw-restricted-rules.restrictedConditions.relation.${relation}`, {
                        assignments: `"${this.$t(snippet, 1)}"`,
                    });
                })
                .join('<br />');

            return {
                disabled: false,
                width: 260,
                message: this.$t('sw-restricted-rules.restrictedConditions.restrictedConditionTooltip', { assignments }),
            };
        },

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
