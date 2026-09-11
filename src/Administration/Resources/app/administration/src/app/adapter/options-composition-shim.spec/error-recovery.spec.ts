/**
 * @sw-package framework
 */

import { createExtendableSetup, _overridesMap } from 'src/app/adapter/composition-extension-system';

import { mount } from '@vue/test-utils';
import { ref, defineComponent } from 'vue';

import { convertWithSilencedWarning } from './fixtures';

describe('Options composition shim', () => {
    beforeEach(() => {
        const entries = [...Object.keys(_overridesMap)];
        entries.forEach((key) => {
            delete _overridesMap[key];
        });

        jest.clearAllMocks();
    });
    describe('Error recovery:', () => {
        it('should propagate errors thrown in created without catching them, and still apply subsequent overrides', async () => {
            const capturedErrors: unknown[] = [];

            const originalComponent = defineComponent({
                template: '<div class="count">{{ count }}</div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const count = ref(0);
                        return { public: { count } };
                    }),
            });

            const wrapper = mount(originalComponent, {
                global: {
                    config: {
                        errorHandler: (err: unknown) => {
                            capturedErrors.push(err);
                        },
                    },
                },
            });

            // Override that throws in created - matching Vue's native behavior, the error
            // must propagate and the override must not be applied (count stays at 0).
            const failingOverride = convertWithSilencedWarning('originalComponent', {
                data() {
                    return { count: 50 };
                },
                created() {
                    throw new Error('Simulated error in created hook');
                },
            });

            _overridesMap.originalComponent.push(failingOverride);

            await flushPromises();

            expect(capturedErrors).toHaveLength(1);
            expect(capturedErrors[0]).toBeInstanceOf(Error);
            expect((capturedErrors[0] as Error).message).toBe('Simulated error in created hook');

            // Override data must not be applied because the override function threw before returning
            expect(wrapper.find('.count').text()).toBe('0');

            // Subsequent overrides must still apply — the failing one is marked as done to prevent retries
            const successOverride = convertWithSilencedWarning('originalComponent', {
                data() {
                    return { count: 100 };
                },
            });

            _overridesMap.originalComponent.push(successOverride);

            await flushPromises();

            expect(wrapper.find('.count').text()).toBe('100');
        });
    });
});
