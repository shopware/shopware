/**
 * @sw-package fundamentals@after-sales
 *
 * @experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES
 */
import { computed, type ComputedRef, type WritableComputedRef } from 'vue';

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
 * The mixin read `this.condition` and called the host's `ensureValueExist()` before writing to it. Both
 * are passed in, because a composable has neither.
 *
 * @private
 */
export interface UseRuleBetweenOperatorOptions {
    condition: () => RuleCondition | null | undefined;
    ensureValueExist: () => void;
}

/**
 * Composable alternative to the `rule-between-operator` mixin: renders a condition's `between` operator
 * as a from/to pair on a date or datetime field. The mixin stays in place for Options API components.
 *
 * Keep this and `src/app/mixin/rule-between-operator.mixin.ts` in sync — change both together.
 *
 * @private
 */
export default function useRuleBetweenOperator(options: UseRuleBetweenOperatorOptions): {
    isBetween: ComputedRef<boolean>;
    betweenValue: WritableComputedRef<BetweenValue>;
} {
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
