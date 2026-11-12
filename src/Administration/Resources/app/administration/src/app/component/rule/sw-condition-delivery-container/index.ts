import type { PropType } from 'vue';
import type RuleConditionService from 'src/app/service/rule-condition.service';
import useRuleContainer, { type RuleConditionNode } from 'src/app/composables/use-rule-container';
import template from './sw-condition-delivery-container.html.twig';

type RuleCondition = EntitySchema.Entity<'rule_condition'>;

/**
 * @private
 * @sw-package fundamentals@after-sales
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    provide() {
        return {
            unwrapDeliveryCondition: (childCondition: RuleCondition) => this.unwrapCondition(childCondition),
        };
    },

    emits: ['create-before', 'create-after'],

    props: {
        condition: {
            type: Object as PropType<RuleConditionNode>,
            required: true,
        },

        parentCondition: {
            type: Object as PropType<RuleConditionNode>,
            required: true,
        },

        level: {
            type: Number,
            required: true,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    setup(props) {
        return useRuleContainer({
            condition: () => props.condition,
            level: () => props.level,
            disabled: () => props.disabled,
            onAddPlaceholder: () => {},
        });
    },

    computed: {
        children(): EntityCollection<'rule_condition'> {
            return this.condition[this.childAssociationField] as EntityCollection<'rule_condition'>;
        },

        childType(): string | null {
            return this.children.first()?.type ?? null;
        },

        childrenLength(): number {
            return this.children.length;
        },
    },

    watch: {
        childType(type: string | null) {
            const child = this.children.first();

            if (!type || !child) {
                return;
            }

            const conditionDataProviderService = this.conditionDataProviderService as RuleConditionService;

            if (conditionDataProviderService.getByType(type)?.component !== 'sw-condition-generic-delivery') {
                this.unwrapCondition(child);
            }
        },

        childrenLength(length: number) {
            if (length === 0) {
                this.removeNodeFromTree(this.parentCondition, this.condition);
            }
        },
    },

    methods: {
        unwrapCondition(childCondition: RuleCondition): void {
            this.removeNodeFromTree(this.parentCondition, this.condition);
            this.insertNodeIntoTree(
                this.parentCondition,
                this.createCondition(
                    {
                        ...(this.conditionDataProviderService as RuleConditionService).getMovableConditionData(
                            childCondition,
                        ),
                        [this.childAssociationField]: new Shopware.Data.EntityCollection(
                            this.children.source,
                            this.children.entity,
                            this.children.context,
                            null,
                            [],
                        ),
                    },
                    this.parentCondition.id ?? null,
                    this.condition.position as number,
                ),
            );
        },

        onInsertBefore(): void {
            this.$emit('create-before');
        },

        onInsertAfter(): void {
            this.$emit('create-after');
        },
    },
});
