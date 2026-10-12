/**
 * @sw-package framework
 *
 * @experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES
 */
import { computed, inject, unref, watch, type ComputedRef } from 'vue';

/** @private */
export interface RuleConditionNode {
    id?: string;
    [key: string]: unknown;
}

/** @private */
export interface ContainerRowClass {
    'is--disabled': boolean;
    'container-condition-level__is--odd'?: boolean;
    'container-condition-level__is--even'?: boolean;
}

/**
 * The mixin declared the `condition`, `level` and `disabled` props itself and watched `nextPosition` to
 * call the host's `onAddPlaceholder()`. The props arrive as getters and the watcher callback as an
 * option, because a composable can declare neither.
 *
 * @private
 */
export interface UseRuleContainerOptions {
    condition: () => RuleConditionNode;
    level: () => number;
    disabled: () => boolean;
    onAddPlaceholder: () => void;
}

/** @private */
export interface UseRuleContainerReturn {
    conditionDataProviderService: ComputedRef<unknown>;
    childAssociationField: ComputedRef<string>;
    createCondition: (conditionData: unknown, parentId: string | null, position: number) => RuleConditionNode;
    insertNodeIntoTree: (parentCondition: RuleConditionNode, childToInsert: RuleConditionNode) => void;
    removeNodeFromTree: (parentCondition: RuleConditionNode, childToRemove: RuleConditionNode) => void;
    containerRowClass: ComputedRef<ContainerRowClass>;
    nextPosition: ComputedRef<number>;
}

/**
 * Composable alternative to the `ruleContainer` mixin: the shared state of a condition container inside
 * `sw-condition-tree`. The mixin stays in place for Options API components.
 *
 * `sw-condition-tree` provides `childAssociationField` and `conditionDataProviderService` as computed
 * refs. The Options API unwrapped them on the instance proxy; here they stay refs and are returned as
 * such.
 *
 * Keep this and `src/app/mixin/rule-container.mixin.ts` in sync — change both together.
 *
 * @private
 */
export default function useRuleContainer(options: UseRuleContainerOptions): UseRuleContainerReturn {
    const injectedDataProviderService = inject<unknown>('conditionDataProviderService');
    const injectedChildAssociationField = inject<string | ComputedRef<string>>('childAssociationField', '');
    const createCondition = inject<UseRuleContainerReturn['createCondition']>('createCondition');
    const insertNodeIntoTree = inject<UseRuleContainerReturn['insertNodeIntoTree']>('insertNodeIntoTree');
    const removeNodeFromTree = inject<UseRuleContainerReturn['removeNodeFromTree']>('removeNodeFromTree');

    const conditionDataProviderService = computed(() => unref(injectedDataProviderService));
    const childAssociationField = computed(() => unref(injectedChildAssociationField));

    const containerRowClass = computed<ContainerRowClass>(() => {
        const classes: ContainerRowClass = {
            'is--disabled': options.disabled(),
        };

        const level = options.level() % 2 ? 'container-condition-level__is--odd' : 'container-condition-level__is--even';

        classes[level] = true;

        return classes;
    });

    const nextPosition = computed(() => {
        const children = options.condition()[childAssociationField.value] as { length: number } | undefined;

        if (children && children.length > 0) {
            return children.length;
        }

        return 0;
    });

    watch(nextPosition, () => {
        if (nextPosition.value === 0) {
            options.onAddPlaceholder();
        }
    });

    return {
        conditionDataProviderService,
        childAssociationField,
        createCondition: createCondition as UseRuleContainerReturn['createCondition'],
        insertNodeIntoTree: insertNodeIntoTree as UseRuleContainerReturn['insertNodeIntoTree'],
        removeNodeFromTree: removeNodeFromTree as UseRuleContainerReturn['removeNodeFromTree'],
        containerRowClass,
        nextPosition,
    };
}
