/**
 * @sw-package framework
 */

/* eslint-disable @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-explicit-any */

import { createExtendableSetup, _overridesMap } from 'src/app/adapter/composition-extension-system';
import { convertOptionsApiOverrideToCompositionApi } from 'src/app/adapter/options-composition-shim';

import { mount } from '@vue/test-utils';
import { ref, defineComponent, nextTick } from 'vue';

describe('Options composition shim', () => {
    beforeEach(() => {
        const entries = [...Object.keys(_overridesMap)];
        entries.forEach((key) => {
            delete _overridesMap[key];
        });

        jest.clearAllMocks();
    });
    describe('setupWatchers() — dot-notation paths:', () => {
        it('should accept dot paths with missing intermediate values', async () => {
            // Use a single spy that covers all console.warn calls to avoid nested-spy issues.
            const consoleWarn = jest.spyOn(console, 'warn').mockImplementation(() => {});
            const watchCallback = jest.fn();

            const originalComponent = defineComponent({
                template: '<div></div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        return { public: {} };
                    }),
            });

            mount(originalComponent);

            // Call convertOptionsApiOverrideToCompositionApi directly so the deprecation
            // warning is also captured by our single spy (filtered out below).
            const overrideFn = convertOptionsApiOverrideToCompositionApi('originalComponent', {
                watch: {
                    'user.name'(newVal: any) {
                        watchCallback(newVal);
                    },
                },
            });

            _overridesMap.originalComponent.push(overrideFn);

            await flushPromises();

            const dotNotationWarnings = consoleWarn.mock.calls.filter(
                (call) => typeof call[0] === 'string' && call[0].includes('Dot-notation watch path'),
            );

            expect(dotNotationWarnings).toHaveLength(0);
            expect(watchCallback).not.toHaveBeenCalled();

            consoleWarn.mockRestore();
        });

        it('should still process non-dot-notation watch keys alongside dot-notation ones', async () => {
            const consoleWarn = jest.spyOn(console, 'warn').mockImplementation(() => {});
            const flatCallback = jest.fn();

            const originalComponent = defineComponent({
                template: '<div class="count">{{ count }}</div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const count = ref(0);
                        return { public: { count } };
                    }),
            });

            const wrapper = mount(originalComponent);

            const overrideFn = convertOptionsApiOverrideToCompositionApi('originalComponent', {
                watch: {
                    'nested.prop'() {},
                    count(newVal: number) {
                        flatCallback(newVal);
                    },
                },
            });

            _overridesMap.originalComponent.push(overrideFn);

            await flushPromises();

            // Trigger a count change to fire the valid watcher
            _overridesMap.originalComponent.push((previousState: any) => {
                previousState.count.value = 99;
                return {};
            });

            await flushPromises();
            await nextTick();

            expect(flatCallback).toHaveBeenCalledWith(99);

            const dotNotationWarnings = consoleWarn.mock.calls.filter(
                (call) => typeof call[0] === 'string' && call[0].includes('Dot-notation watch path'),
            );
            expect(dotNotationWarnings).toHaveLength(0);

            consoleWarn.mockRestore();
            wrapper.unmount();
        });
    });
});
