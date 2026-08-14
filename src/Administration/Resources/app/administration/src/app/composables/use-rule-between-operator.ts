/**
 * @sw-package fundamentals@after-sales
 */
import { computed } from 'vue';
import type { ComputedRef, WritableComputedRef } from 'vue';

/** @private */
export type BetweenValue = {
    from: string | null;
    to: string | null;
};

interface RuleCondition {
    value?: {
        operator?: string;
        renderedFieldValue?: unknown;
        [key: string]: unknown;
    } | null;
}

/**
 * The mixin read `this.condition` and called the host's `ensureValueExist()`
 * before writing to it. Both are passed in, because a composable has neither.
 *
 * @private
 */
export interface UseRuleBetweenOperatorOptions {
    condition: () => RuleCondition | null | undefined;
    ensureValueExist: () => void;
}

/** @private */
export interface UseRuleBetweenOperatorReturn {
    isBetween: ComputedRef<boolean>;
    betweenValue: WritableComputedRef<BetweenValue>;
}

/**
 * Composable alternative to the `rule-between-operator` mixin: renders a
 * condition's `between` operator as a from/to pair on a date or datetime field.
 *
 * Keep this and `src/app/mixin/rule-between-operator.mixin.ts` in sync —
 * change both together.
 *
 * @private
 */
export function useRuleBetweenOperator(options: UseRuleBetweenOperatorOptions): UseRuleBetweenOperatorReturn {
    const isBetween = computed(() => options.condition()?.value?.operator === 'between');

    const betweenValue = computed<BetweenValue>({
        get: () => {
            const raw: unknown = options.condition()?.value?.renderedFieldValue;

            if (raw === null || typeof raw !== 'object' || Array.isArray(raw)) {
                return { from: null, to: null };
            }

            const value = raw as Partial<BetweenValue>;

            return {
                from: value.from ?? null,
                to: value.to ?? null,
            };
        },
        set: (value: BetweenValue) => {
            options.ensureValueExist();

            const condition = options.condition();
            if (!condition) {
                return;
            }

            condition.value = {
                ...condition.value,
                renderedFieldValue: value,
            };
        },
    });

    return { isBetween, betweenValue };
}
