/**
 * @sw-package framework
 */

import { createExtendableSetup, _overridesMap } from 'src/app/adapter/composition-extension-system';

import { mount } from '@vue/test-utils';
import { ref, defineComponent, nextTick, reactive } from 'vue';

import { convertWithSilencedWarning } from './fixtures';

describe('Options composition shim', () => {
    beforeEach(() => {
        const entries = [...Object.keys(_overridesMap)];
        entries.forEach((key) => {
            delete _overridesMap[key];
        });

        jest.clearAllMocks();
    });
    describe('Component unmount cleanup:', () => {
        it('should not fire watchers after component unmount', async () => {
            const watchCallback = jest.fn();

            const originalComponent = defineComponent({
                template: '<div class="count">{{ count }}</div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const count = ref(0);
                        return { public: { count } };
                    }),
            });

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                watch: {
                    count(newVal: number) {
                        watchCallback(newVal);
                    },
                },
            });

            _overridesMap.originalComponent = reactive([]);
            _overridesMap.originalComponent.push(overrideFn);

            const wrapper = mount(originalComponent);

            await flushPromises();

            wrapper.unmount();

            watchCallback.mockClear();

            await nextTick();

            expect(watchCallback).not.toHaveBeenCalled();
        });
    });
});
