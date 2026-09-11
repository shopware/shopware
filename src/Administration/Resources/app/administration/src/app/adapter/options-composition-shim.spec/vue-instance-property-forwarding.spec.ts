/**
 * @sw-package framework
 */

/* eslint-disable @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-call, @typescript-eslint/no-explicit-any */

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
    describe('Vue instance property forwarding:', () => {
        it('should forward this.$emit() to the component instance', async () => {
            const originalComponent = defineComponent({
                template: '<button class="btn" @click="doEmit">Emit</button>',
                emits: ['custom-event'],
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const doEmit = () => {};
                        return { public: { doEmit } };
                    }),
            });

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                methods: {
                    doEmit() {
                        this.$emit('custom-event', 'payload');
                    },
                },
            });

            // Push BEFORE mount so the proxy captures the component instance during setup
            _overridesMap.originalComponent = reactive([]);
            _overridesMap.originalComponent.push(overrideFn);

            const wrapper = mount(originalComponent);

            await flushPromises();

            await wrapper.find('.btn').trigger('click');

            expect(wrapper.emitted('custom-event')).toBeTruthy();
            expect(wrapper.emitted('custom-event')![0]).toEqual(['payload']);
        });

        it('should forward this.$nextTick() to the component instance', async () => {
            let nextTickResolved = false;

            const originalComponent = defineComponent({
                template: '<div class="count">{{ count }}</div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const count = ref(0);
                        return { public: { count } };
                    }),
            });

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                async created() {
                    if (this.$nextTick) {
                        await this.$nextTick(() => {
                            nextTickResolved = true;
                        });
                    }
                },
            });

            // Push BEFORE mount so the proxy captures the component instance during setup
            _overridesMap.originalComponent = reactive([]);
            _overridesMap.originalComponent.push(overrideFn);

            mount(originalComponent);

            await flushPromises();
            await nextTick();

            expect(nextTickResolved).toBe(true);
        });

        it('should forward this.$refs to the component instance', async () => {
            let capturedRef: any = null;

            const originalComponent = defineComponent({
                template: '<div ref="myDiv" class="target">hello</div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        return { public: {} };
                    }),
            });

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                mounted() {
                    capturedRef = this.$refs;
                },
            });

            _overridesMap.originalComponent = reactive([]);
            _overridesMap.originalComponent.push(overrideFn);

            mount(originalComponent);

            await flushPromises();
            await nextTick();

            expect(capturedRef).toBeDefined();
            expect(capturedRef).not.toBeNull();
        });
    });
});
