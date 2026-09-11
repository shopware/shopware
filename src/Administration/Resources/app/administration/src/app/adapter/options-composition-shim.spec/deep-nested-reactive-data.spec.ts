/**
 * @sw-package framework
 */

/* eslint-disable @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-explicit-any */

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
    describe('Deep nested reactive data:', () => {
        it('should maintain reactivity for deeply nested object data', async () => {
            const originalComponent = defineComponent({
                template: '<div class="city">{{ address.city }}</div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const address = ref({ city: 'initial' });
                        return { public: { address } };
                    }),
            });

            const wrapper = mount(originalComponent);

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                data() {
                    return { address: { city: 'Berlin' } };
                },
            });

            _overridesMap.originalComponent.push(overrideFn);

            await flushPromises();

            expect(wrapper.find('.city').text()).toBe('Berlin');
        });

        it('should handle nested object data with deep reactivity', () => {
            const overrideFn = convertWithSilencedWarning('originalComponent', {
                data() {
                    return {
                        user: { address: { city: 'Berlin' } },
                    };
                },
            });

            const result = overrideFn({}, {}) as Record<string, any>;

            expect(result.user.value.address.city).toBe('Berlin');

            result.user.value.address.city = 'Hamburg';
            expect(result.user.value.address.city).toBe('Hamburg');
        });
    });
});
