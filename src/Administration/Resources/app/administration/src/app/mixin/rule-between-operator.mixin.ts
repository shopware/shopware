import { defineComponent } from 'vue';

/**
 * @private
 */
export const RULE_BETWEEN_OPERATOR_MIXIN_NAME = 'rule-between-operator';

type BetweenValue = {
    from: string | null;
    to: string | null;
};

/**
 * @private
 * @sw-package fundamentals@after-sales
 *
 * Adds `isBetween` and `betweenValue` to a condition component so
 * it can render a two picker ui for the `between` operator on a date / datetime
 * field.
 */
export default Shopware.Mixin.register(
    RULE_BETWEEN_OPERATOR_MIXIN_NAME,
    defineComponent({
        computed: {
            isBetween(): boolean {
                // @ts-expect-error
                // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
                return this.condition?.value?.operator === 'between';
            },

            betweenValue: {
                get(): BetweenValue {
                    // @ts-expect-error
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
                    const raw: unknown = this.condition?.value?.renderedFieldValue;

                    if (raw === null || typeof raw !== 'object' || Array.isArray(raw)) {
                        return {
                            from: null,
                            to: null,
                        };
                    }

                    const value = raw as Partial<BetweenValue>;

                    return {
                        from: value.from ?? null,
                        to: value.to ?? null,
                    };
                },
                set(value: BetweenValue) {
                    // @ts-expect-error
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-call
                    this.ensureValueExist();

                    // @ts-expect-error
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-member-access
                    this.condition.value = {
                        // @ts-expect-error
                        // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
                        ...this.condition.value,
                        renderedFieldValue: value,
                    };
                },
            },
        },
    }),
);
