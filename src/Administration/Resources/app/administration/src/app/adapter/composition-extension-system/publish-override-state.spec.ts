/** @sw-package framework */
import { reactive } from 'vue';
import { publishOverrideState } from './publish-override-state';

describe('reactive override state publication', () => {
    it('accepts a new reactive object without requiring a predecessor', () => {
        const rawState: Record<string, unknown> = {};
        const state = reactive(rawState);
        const extra = reactive({ title: 'plugin' });
        publishOverrideState({ componentName: 'new-object', props: {}, result: { extra }, rawState, state, instance: null });
        expect(state.extra).toBe(extra);
    });

    it('validates cyclic structures without recursion overflow and retains the existing root identity', () => {
        type Node = { title: string; self?: Node };
        const original: Node = { title: 'original' };
        original.self = original;
        const replacement: Node = { title: 'replacement' };
        replacement.self = replacement;
        const rawState = { item: reactive(original) };
        const state = reactive(rawState);
        const identity = state.item;
        publishOverrideState({
            componentName: 'cyclic-object',
            props: {},
            result: { item: reactive(replacement) },
            rawState,
            state,
            instance: null,
        });
        expect(state.item).toBe(identity);
        expect(state.item.title).toBe('replacement');
    });

    it('still rejects a replacement that removes an existing nested property', () => {
        const error = jest.spyOn(console, 'error').mockImplementation(() => {});
        const rawState = { item: reactive({ nested: { retained: true } }) };
        const state = reactive(rawState);
        try {
            publishOverrideState({
                componentName: 'invalid-object',
                props: {},
                result: { item: reactive({ nested: {} }) },
                rawState,
                state,
                instance: null,
            });
            expect(state.item.nested.retained).toBe(true);
            expect(error).toHaveBeenCalledWith(expect.stringContaining('item.nested.retained'));
        } finally {
            error.mockRestore();
        }
    });
});
