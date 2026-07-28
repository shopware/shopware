/**
 * @sw-package framework
 */

import { defineComponent, ref } from 'vue';
import { mount } from '@vue/test-utils';
import { overrideComponentSetup, _overridesMap } from 'src/app/adapter/composition-extension-system';
import {
    applyOverridesForOptionsHost,
    _resetOptionsHostOverrides,
} from 'src/app/adapter/composition-extension-system/options-host-overrides';
import getBlockDataScope from 'src/app/component/structure/sw-block-override/sw-block/get-block-data-scope';

describe('src/app/adapter/composition-extension-system/options-host-overrides', () => {
    beforeEach(() => {
        Object.keys(_overridesMap).forEach((key) => {
            delete _overridesMap[key];
        });
        _resetOptionsHostOverrides();
        jest.clearAllMocks();
    });

    it('returns null when no native override targets the component', () => {
        expect(applyOverridesForOptionsHost('optionsHost', { headline: 'Base' })).toBeNull();
    });

    it('collects override-local state and lets previous-state reads reach Options data', () => {
        let observedHeadline: unknown;

        overrideComponentSetup()('optionsHost', (previousState) => {
            observedHeadline = (previousState as unknown as Record<string, { value: unknown }>).headline.value;

            return {
                __swOverride: {
                    'plugin/file-a': { pluginMessage: 'Hello from A' },
                },
            } as never;
        });

        const localState = applyOverridesForOptionsHost('optionsHost', { headline: 'Options headline' });

        expect(observedHeadline).toBe('Options headline');
        expect(localState).toEqual({
            'plugin/file-a': { pluginMessage: 'Hello from A' },
        });
    });

    it('applies each override once and merges several override-file namespaces', () => {
        const firstOverride = jest.fn(() => ({ __swOverride: { 'plugin/a': { count: 1 } } }));
        const secondOverride = jest.fn(() => ({ __swOverride: { 'plugin/b': { count: 2 } } }));

        overrideComponentSetup()('optionsHost', firstOverride as never);
        overrideComponentSetup()('optionsHost', secondOverride as never);

        const first = applyOverridesForOptionsHost('optionsHost', {});
        const second = applyOverridesForOptionsHost('optionsHost', {});

        expect(first).toBe(second);
        expect(firstOverride).toHaveBeenCalledTimes(1);
        expect(secondOverride).toHaveBeenCalledTimes(1);
        expect(first).toEqual({
            'plugin/a': { count: 1 },
            'plugin/b': { count: 2 },
        });
    });

    it('reports and skips state-replacing keys instead of mutating the Options component', () => {
        const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

        overrideComponentSetup()('optionsHost', () => {
            return {
                headline: ref('Replaced'),
                __swOverride: { 'plugin/a': { kept: true } },
            } as never;
        });

        const localState = applyOverridesForOptionsHost('optionsHost', { headline: 'Base' });

        expect(localState).toEqual({ 'plugin/a': { kept: true } });
        expect(errorSpy).toHaveBeenCalledWith(expect.stringContaining('cannot replace state of an Options API component'));
    });

    it('reports a throwing override and still applies the remaining ones', () => {
        const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

        overrideComponentSetup()('optionsHost', () => {
            throw new Error('broken override');
        });
        overrideComponentSetup()('optionsHost', () => {
            return { __swOverride: { 'plugin/b': { alive: true } } } as never;
        });

        const localState = applyOverridesForOptionsHost('optionsHost', {});

        expect(localState).toEqual({ 'plugin/b': { alive: true } });
        expect(errorSpy).toHaveBeenCalledWith(
            expect.stringContaining('could not be applied on this Options API component'),
            expect.any(Error),
        );
    });

    describe('getBlockDataScope on an Options API host', () => {
        it('exposes the __swOverride channel while delegating other reads to the instance proxy', () => {
            overrideComponentSetup()('optionsHost', () => {
                return { __swOverride: { 'plugin/a': { pluginMessage: 'Forwarded' } } } as never;
            });

            let scope: Record<string, unknown> | null = null;

            const host = defineComponent({
                name: 'optionsHost',
                template: '<div>{{ headline }}</div>',
                data() {
                    return { headline: 'Options headline' };
                },
                mounted() {
                    scope = getBlockDataScope.call(this) as Record<string, unknown>;
                },
            });

            mount(host);

            expect(scope).not.toBeNull();
            expect(scope!.headline).toBe('Options headline');
            expect(scope!.__swOverride).toEqual({ 'plugin/a': { pluginMessage: 'Forwarded' } });
        });

        it('keeps returning the plain proxy when no override targets the host', () => {
            let scope: Record<string, unknown> | null = null;

            const host = defineComponent({
                name: 'untouchedOptionsHost',
                template: '<div>{{ headline }}</div>',
                data() {
                    return { headline: 'Untouched' };
                },
                mounted() {
                    scope = getBlockDataScope.call(this) as Record<string, unknown>;
                },
            });

            mount(host);

            expect(scope).not.toBeNull();
            expect(scope!.headline).toBe('Untouched');
            expect(scope!.__swOverride).toBeUndefined();
        });
    });
});
