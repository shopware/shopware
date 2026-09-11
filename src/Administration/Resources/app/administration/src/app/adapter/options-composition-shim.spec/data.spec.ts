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
    describe('convertData():', () => {
        it('should convert data() overriding an existing ref value', async () => {
            const originalComponent = defineComponent({
                template: '<div><span class="msg">{{ message }}</span></div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const message = ref('original');

                        return {
                            public: { message },
                        };
                    }),
            });

            const wrapper = mount(originalComponent);
            expect(wrapper.find('.msg').text()).toBe('original');

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                data() {
                    return { message: 'overridden' };
                },
            });

            _overridesMap.originalComponent.push(overrideFn);

            await flushPromises();

            expect(wrapper.find('.msg').text()).toBe('overridden');
        });

        it('should convert data() return values to refs', () => {
            const overrideFn = convertWithSilencedWarning('originalComponent', {
                data() {
                    return { count: 42, name: 'test' };
                },
            });

            const result = overrideFn({}, {}) as Record<string, any>;

            expect(result.count.value).toBe(42);
            expect(result.name.value).toBe('test');
        });
    });
});
