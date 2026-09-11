/**
 * @sw-package framework
 */

/* eslint-disable @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-explicit-any */

import { createExtendableSetup, _overridesMap } from 'src/app/adapter/composition-extension-system';

import { mount } from '@vue/test-utils';
import { ref, defineComponent, nextTick } from 'vue';

import { convertWithSilencedWarning } from './fixtures';

describe('Options composition shim', () => {
    beforeEach(() => {
        const entries = [...Object.keys(_overridesMap)];
        entries.forEach((key) => {
            delete _overridesMap[key];
        });

        jest.clearAllMocks();
    });
    describe('Watch flush option:', () => {
        it('should forward flush option to Vue watch', async () => {
            const watchCallback = jest.fn();
            let domTextDuringCallback: string | null = null;

            const originalComponent = defineComponent({
                template: '<div class="count">{{ count }}</div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const count = ref(0);
                        return { public: { count } };
                    }),
            });

            const wrapper = mount(originalComponent);

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                watch: {
                    count: {
                        handler(newVal: number) {
                            // With flush: 'post', the DOM should already be updated when this callback fires.
                            // With flush: 'pre' (default), domTextDuringCallback would still be '0'.
                            domTextDuringCallback = wrapper.find('.count').text();
                            watchCallback(newVal);
                        },
                        flush: 'post',
                    },
                },
            });

            _overridesMap.originalComponent.push(overrideFn);

            await flushPromises();

            _overridesMap.originalComponent.push((previousState: any) => {
                previousState.count.value = 42;
                return {};
            });

            await flushPromises();
            await nextTick();

            expect(watchCallback).toHaveBeenCalledWith(42);
            // Verify flush: 'post' timing — the DOM must already show the updated value
            // when the callback fires (this assertion would fail with flush: 'pre').
            expect(domTextDuringCallback).toBe('42');
        });
    });
});
