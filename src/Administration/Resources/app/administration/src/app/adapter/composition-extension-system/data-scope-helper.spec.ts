/**
 * @sw-package framework
 */

import type { ComponentInternalInstance } from '@vue/runtime-core';
import { computed, isReactive, reactive, ref } from 'vue';
import {
    createDataScope,
    createOverrideLocalState,
    exposeOverrideLocalState,
    getOverrideLocalState,
    getScriptSetupDataScope,
    isOverrideLocalStateKey,
    mergeOverrideState,
    OVERRIDE_LOCAL_STATE_KEY,
    setDataScopeForInstance,
} from './data-scope-helper';
import type { OverrideLocalState } from './data-scope-helper';

describe('src/app/adapter/composition-extension-system/data-scope-helper', () => {
    it('exposes override-local state as a hidden setup-state property', () => {
        const setupState = {
            headline: 'Base headline',
        };
        const overrideLocalState = createOverrideLocalState();

        exposeOverrideLocalState(setupState, overrideLocalState);

        expect(Object.keys(setupState)).toEqual(['headline']);
        expect(getOverrideLocalState(setupState)).toBe(overrideLocalState);
        expect(isReactive(getOverrideLocalState(setupState))).toBe(true);
    });

    it('merges override-local namespaces into the same reactive state object', () => {
        const overrideLocalState = createOverrideLocalState();

        mergeOverrideState(overrideLocalState, {
            firstOverrideFile: {
                pluginMessage: 'First message',
            },
        });

        mergeOverrideState(overrideLocalState, {
            secondOverrideFile: {
                pluginMessage: 'Second message',
            },
        });

        expect(overrideLocalState).toEqual({
            firstOverrideFile: {
                pluginMessage: 'First message',
            },
            secondOverrideFile: {
                pluginMessage: 'Second message',
            },
        });
    });

    it('creates a proxy-compatible data scope for the current component instance', () => {
        const setupState = {
            headline: 'Base headline',
        };
        const overrideLocalState = createOverrideLocalState();
        const instance = {} as ComponentInternalInstance;

        exposeOverrideLocalState(setupState, overrideLocalState);
        mergeOverrideState(overrideLocalState, {
            pluginOverrideFile: {
                pluginMessage: 'Plugin message',
            },
        });

        const dataScope = createDataScope(reactive(setupState));

        expect(Object.keys(dataScope)).toEqual(['headline']);
        expect(dataScope[OVERRIDE_LOCAL_STATE_KEY].value).toBe(overrideLocalState);

        setDataScopeForInstance(instance, dataScope);

        const registeredDataScope = getScriptSetupDataScope(instance) as {
            headline: string;
            [OVERRIDE_LOCAL_STATE_KEY]: OverrideLocalState;
        };

        expect(registeredDataScope.headline).toBe('Base headline');
        expect(registeredDataScope[OVERRIDE_LOCAL_STATE_KEY].pluginOverrideFile.pluginMessage).toBe('Plugin message');
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

    it('recognizes only the reserved override-local state key', () => {
        expect(isOverrideLocalStateKey(OVERRIDE_LOCAL_STATE_KEY)).toBe(true);
        expect(isOverrideLocalStateKey('headline')).toBe(false);
    });
});
