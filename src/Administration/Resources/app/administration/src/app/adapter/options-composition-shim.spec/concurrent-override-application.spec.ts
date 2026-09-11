/**
 * @sw-package framework
 */

/* eslint-disable @typescript-eslint/no-unsafe-member-access */

import { createExtendableSetup, _overridesMap } from 'src/app/adapter/composition-extension-system';

import { mount } from '@vue/test-utils';
import { ref, computed, defineComponent } from 'vue';

import { convertWithSilencedWarning } from './fixtures';

describe('Options composition shim', () => {
    beforeEach(() => {
        const entries = [...Object.keys(_overridesMap)];
        entries.forEach((key) => {
            delete _overridesMap[key];
        });

        jest.clearAllMocks();
    });
    describe('Concurrent override application:', () => {
        it('should apply two overrides pushed in the same tick', async () => {
            const originalComponent = defineComponent({
                template: `
                    <div class="count">{{ count }}</div>
                    <div class="label">{{ label }}</div>
                `,
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const count = ref(0);
                        const label = computed(() => `Value: ${count.value}`);
                        return { public: { count, label } };
                    }),
            });

            const wrapper = mount(originalComponent);

            const overrideA = convertWithSilencedWarning('originalComponent', {
                data() {
                    return { count: 42 };
                },
            });

            const overrideB = convertWithSilencedWarning('originalComponent', {
                computed: {
                    label() {
                        return `Custom: ${this.count}`;
                    },
                },
            });

            // Push both in the same tick
            _overridesMap.originalComponent.push(overrideA);
            _overridesMap.originalComponent.push(overrideB);

            await flushPromises();

            expect(wrapper.find('.count').text()).toBe('42');
            expect(wrapper.find('.label').text()).toBe('Custom: 42');
        });
    });
});
