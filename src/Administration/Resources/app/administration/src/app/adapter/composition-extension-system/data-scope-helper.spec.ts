/**
 * @sw-package framework
 */

import type { ComponentInternalInstance } from '@vue/runtime-core';
import { computed, reactive, ref } from 'vue';
import { createDataScope, getScriptSetupDataScope, setDataScopeForInstance } from './data-scope-helper';

describe('src/app/adapter/composition-extension-system/data-scope-helper', () => {
    it('creates a proxy-compatible data scope for the current component instance', () => {
        const instance = {} as ComponentInternalInstance;
        const dataScope = createDataScope(reactive({ headline: 'Base headline' }));

        expect(Object.keys(dataScope)).toEqual(['headline']);

        setDataScopeForInstance(instance, dataScope);

        const registeredDataScope = getScriptSetupDataScope(instance) as { headline: string };

        expect(registeredDataScope.headline).toBe('Base headline');
    });

    it('keeps computeds lazy while building the data scope', () => {
        const evaluate = jest.fn(() => 'Derived headline');
        const reactiveSetupState = reactive({
            headline: ref('Base headline'),
            derivedHeadline: computed(evaluate),
        });

        const dataScope = createDataScope(reactiveSetupState);

        // `toRefs()` used to read every key to test it for `isRef`, which unwrapped - and therefore ran -
        // every computed inside `setup()`, before any lifecycle hook could initialize what it reads.
        expect(evaluate).not.toHaveBeenCalled();

        expect(dataScope.derivedHeadline.value).toBe('Derived headline');
        expect(evaluate).toHaveBeenCalledTimes(1);
    });

    it('reads and writes state through the reactive source', () => {
        const headline = ref('Base headline');
        const reactiveSetupState = reactive({ headline });

        const dataScope = createDataScope(reactiveSetupState);

        expect(dataScope.headline.value).toBe('Base headline');

        dataScope.headline.value = 'Written headline';
        expect(headline.value).toBe('Written headline');

        headline.value = 'Updated headline';
        expect(dataScope.headline.value).toBe('Updated headline');
    });
});
