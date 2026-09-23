/**
 * @sw-package framework
 */

import type { ComponentInternalInstance } from '@vue/runtime-core';
import { computed, isRef, reactive, ref } from 'vue';
import {
    createLazyRefs,
    getScriptSetupDataScope,
    OVERRIDE_LOCAL_STATE_KEY,
    setScriptSetupDataScope,
} from './data-scope-helper';

describe('src/app/adapter/composition-extension-system/data-scope-helper', () => {
    it('stores one data scope per instance', () => {
        const instance = {} as ComponentInternalInstance;
        const state = reactive({ headline: 'Headline' });

        expect(getScriptSetupDataScope(instance)).toBeNull();

        setScriptSetupDataScope(instance, state);

        expect(getScriptSetupDataScope(instance)).toBe(state);
    });

    it('creates lazy refs that do not evaluate computeds until they are read', () => {
        const evaluate = jest.fn(() => 'computed');
        const refs = createLazyRefs(reactive({ doubled: computed(evaluate) }));

        expect(isRef(refs.doubled)).toBe(true);
        expect(evaluate).not.toHaveBeenCalled();

        expect(refs.doubled.value).toBe('computed');
        expect(evaluate).toHaveBeenCalledTimes(1);
    });

    it('reads and writes through the reactive state', () => {
        const count = ref(1);
        const state = reactive<Record<string, unknown>>({ count });
        const refs = createLazyRefs(state);

        refs.count.value = 2;
        expect(count.value).toBe(2);

        state.count = ref(10);
        expect(refs.count.value).toBe(10);
    });

    it('adds a non-enumerable ref to the override-local state', () => {
        const overrideLocalState = reactive({});
        const state = reactive<Record<string, unknown>>({ headline: 'Headline' });
        Object.defineProperty(state, OVERRIDE_LOCAL_STATE_KEY, { value: overrideLocalState, enumerable: false });

        const refs = createLazyRefs(state);

        expect(Object.keys(refs)).toEqual(['headline']);
        expect(refs[OVERRIDE_LOCAL_STATE_KEY].value).toBe(overrideLocalState);
    });
});
