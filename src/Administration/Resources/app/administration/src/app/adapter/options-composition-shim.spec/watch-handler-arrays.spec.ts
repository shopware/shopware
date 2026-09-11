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
    describe('Watch handler arrays:', () => {
        it('should support array of handlers for a single watch key', async () => {
            const callback1 = jest.fn();
            const callback2 = jest.fn();

            const originalComponent = defineComponent({
                template: '<div class="count">{{ count }}</div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const count = ref(0);
                        return { public: { count } };
                    }),
            });

            mount(originalComponent);

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                watch: {
                    count: [
                        function (this: any, newVal: number) {
                            callback1(newVal);
                        },
                        {
                            handler(newVal: number) {
                                callback2(newVal);
                            },
                            immediate: true,
                        },
                    ],
                },
            });

            _overridesMap.originalComponent.push(overrideFn);

            await flushPromises();
            await nextTick();

            // The immediate handler should have fired already
            expect(callback2).toHaveBeenCalled();

            // Trigger a change
            _overridesMap.originalComponent.push((previousState: any) => {
                previousState.count.value = 99;
                return {};
            });

            await flushPromises();
            await nextTick();

            expect(callback1).toHaveBeenCalledWith(99);
            expect(callback2).toHaveBeenCalledWith(99);
        });
    });
});
