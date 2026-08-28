/**
 * @sw-package fundamentals@after-sales
 */
import useRuleBetweenOperator from './use-rule-between-operator';

interface TestCondition {
    value?: { operator?: string; renderedFieldValue?: unknown } | null;
}

function createComposable(condition: TestCondition | null): {
    ensureValueExist: jest.Mock;
    composable: ReturnType<typeof useRuleBetweenOperator>;
} {
    const ensureValueExist = jest.fn();

    return {
        ensureValueExist,
        composable: useRuleBetweenOperator({ condition: () => condition, ensureValueExist }),
    };
}

describe('src/app/composables/use-rule-between-operator', () => {
    it('is only between for the between operator', () => {
        expect(createComposable({ value: { operator: 'between' } }).composable.isBetween.value).toBe(true);
        expect(createComposable({ value: { operator: 'gte' } }).composable.isBetween.value).toBe(false);
        expect(createComposable({}).composable.isBetween.value).toBe(false);
        expect(createComposable(null).composable.isBetween.value).toBe(false);
    });

    it('reads the from/to pair off the rendered field value', () => {
        const { composable } = createComposable({
            value: { operator: 'between', renderedFieldValue: { from: '2026-01-01', to: '2026-02-01' } },
        });

        expect(composable.betweenValue.value).toEqual({ from: '2026-01-01', to: '2026-02-01' });
    });

    it.each([
        [
            'a missing value',
            {},
        ],
        [
            'null',
            { value: { renderedFieldValue: null } },
        ],
        [
            'an array',
            { value: { renderedFieldValue: [] } },
        ],
    ])('falls back to an empty pair for %s', (_name, condition) => {
        const { composable } = createComposable(condition);

        expect(composable.betweenValue.value).toEqual({ from: null, to: null });
    });

    it('fills in the missing half of a partial pair', () => {
        const { composable } = createComposable({ value: { renderedFieldValue: { from: '2026-01-01' } } });

        expect(composable.betweenValue.value).toEqual({ from: '2026-01-01', to: null });
    });

    it('lets the caller create the value before writing the pair back', () => {
        const condition: TestCondition = {};
        const ensureValueExist = jest.fn(() => {
            condition.value = { operator: 'between' };
        });
        const composable = useRuleBetweenOperator({ condition: () => condition, ensureValueExist });

        composable.betweenValue.value = { from: '2026-01-01', to: '2026-02-01' };

        expect(ensureValueExist).toHaveBeenCalledTimes(1);
        expect(condition.value).toEqual({
            operator: 'between',
            renderedFieldValue: { from: '2026-01-01', to: '2026-02-01' },
        });
    });
});
