/**
 * @sw-package framework
 */
import * as Vue from 'vue';
import type { ComponentOptions } from 'vue';
import type { SetupHmrMetadata } from 'src/core/factory/async-component.factory';
import type { OverrideFn } from '../options-composition-shim';
import { registerSetupOverrideForHmr, trackSetupHmrComponent } from './script-hmr';

type Definition = ComponentOptions & { __hmrId?: string; __shopwareSetupHmr?: SetupHmrMetadata };

describe('script HMR component lifetimes', () => {
    const cleanups: Array<() => void> = [];
    const runtime = { createRecord: jest.fn(), reload: jest.fn(), rerender: jest.fn() };
    let instance: jest.SpyInstance;

    beforeEach(() => {
        cleanups.length = 0;
        const target = globalThis as typeof globalThis & { __VUE_HMR_RUNTIME__: typeof runtime };
        jest.replaceProperty(target, '__VUE_HMR_RUNTIME__', runtime);
        instance = jest.spyOn(Vue, 'getCurrentInstance');
        jest.spyOn(Vue, 'onUnmounted').mockImplementation((callback: () => void) => {
            cleanups.push(callback);
        });
    });

    afterEach(async () => {
        cleanups.forEach((cleanup) => cleanup());
        await Vue.nextTick();
        jest.restoreAllMocks();
    });

    function current(definition: Definition): void {
        instance.mockReturnValue({ type: definition, parent: null });
    }

    function replaceOverride(name: string): void {
        const overrides: Record<string, OverrideFn[]> = {};
        ['1', '2'].forEach((version) => {
            current({
                __hmrId: `${name}:owner`,
                __shopwareSetupHmr: { componentName: name, mode: 'override', version, invalidate: jest.fn() },
            });
            expect(registerSetupOverrideForHmr(name, () => ({}), overrides)).toBe(true);
        });
        expect(overrides[name]).toHaveLength(1);
    }

    it.activeFeatureFlags(['ADMIN_VITE_8'])('keeps a new mount registered when an old lifetime is disposed again', () => {
        const name = 'sw-hmr-delayed-teardown';
        current({ __hmrId: `${name}:old` });
        trackSetupHmrComponent(name);
        cleanups[0]();
        current({ __hmrId: `${name}:new` });
        trackSetupHmrComponent(name);
        cleanups[0]();
        replaceOverride(name);
        expect(runtime.reload).toHaveBeenCalledTimes(1);
        expect(runtime.reload).toHaveBeenCalledWith(`${name}:new`, expect.any(Object));
    });

    it.activeFeatureFlags(['ADMIN_VITE_8'])(
        'reloads a shared compiler identity once and ignores a withdrawn identity',
        () => {
            const name = 'sw-hmr-shared-identity';
            const withdrawn: Definition = { __hmrId: `${name}:withdrawn` };
            [{ __hmrId: `${name}:shared` }, { __hmrId: `${name}:shared` }, withdrawn].forEach((definition) => {
                current(definition);
                trackSetupHmrComponent(name);
            });
            delete withdrawn.__hmrId;
            replaceOverride(name);
            expect(runtime.reload).toHaveBeenCalledTimes(1);
            expect(runtime.reload).toHaveBeenCalledWith(`${name}:shared`, expect.any(Object));
        },
    );
});
