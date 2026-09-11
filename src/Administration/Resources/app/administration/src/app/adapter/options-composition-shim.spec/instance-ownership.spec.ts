/**
 * @sw-package framework
 */

/* eslint-disable @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-call */

import { createExtendableSetup, _overridesMap } from 'src/app/adapter/composition-extension-system';

import { mount } from '@vue/test-utils';
import { ref, defineComponent, reactive } from 'vue';

import { convertWithSilencedWarning } from './fixtures';

describe('Options composition shim', () => {
    beforeEach(() => {
        const entries = [...Object.keys(_overridesMap)];
        entries.forEach((key) => {
            delete _overridesMap[key];
        });

        jest.clearAllMocks();
    });
    describe('getCurrentInstance behavior:', () => {
        it('should forward Vue instance properties when override is applied during setup (getCurrentInstance returns instance)', async () => {
            // When the override is pre-registered before mount, applyOverrides runs inside the
            // setup() watcher with immediate:true → getCurrentInstance() returns the component
            // instance → $emit/$refs/$nextTick etc. are forwarded via the proxy.
            const originalComponent = defineComponent({
                template: '<button class="btn" @click="doEmit">Emit</button>',
                emits: ['my-event'],
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const doEmit = () => {};
                        return { public: { doEmit } };
                    }),
            });

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                methods: {
                    doEmit() {
                        this.$emit('my-event', 'hello');
                    },
                },
            });

            // Push BEFORE mount so createThisProxy captures the real instance via getCurrentInstance()
            _overridesMap.originalComponent = reactive([]);
            _overridesMap.originalComponent.push(overrideFn);

            const wrapper = mount(originalComponent);
            await flushPromises();

            await wrapper.find('.btn').trigger('click');

            expect(wrapper.emitted('my-event')).toBeTruthy();
            expect(wrapper.emitted('my-event')![0]).toEqual(['hello']);
        });

        it('should retain Vue instance properties when applied late', async () => {
            // When the override is pushed AFTER mount, applyOverrides runs in an async watcher
            // callback outside of setup() → getCurrentInstance() returns null → the proxy cannot
            // access the component instance and returns undefined for $-prefixed properties.
            let capturedEmit: unknown = 'not-set';

            const originalComponent = defineComponent({
                template: '<div class="result">{{ result }}</div>',
                emits: ['my-event'],
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const result = ref('initial');
                        return { public: { result } };
                    }),
            });

            mount(originalComponent);

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                created() {
                    // $emit should be undefined because getCurrentInstance() was null when the
                    // override was applied after mount (outside setup() context).
                    capturedEmit = this.$emit;
                },
                methods: { noop() {} },
            });

            // Push AFTER mount → getCurrentInstance() is null inside createThisProxy
            _overridesMap.originalComponent.push(overrideFn);
            await flushPromises();

            expect(typeof capturedEmit).toBe('function');
        });

        it('should retain ownership of future lifecycle hooks when applied late', async () => {
            // When the override is pushed after mount, getCurrentInstance() is null in
            // setupLifecycleHooks(), so future hooks that cannot be registered via onBeforeUnmount()
            // should emit a console.warn instead of silently being lost.
            const originalComponent = defineComponent({
                template: '<div></div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        return { public: {} };
                    }),
            });

            mount(originalComponent);

            // convertWithSilencedWarning internally calls spy.mockRestore(), so our consoleWarn spy
            // must be set up AFTER the call to avoid it being restored before the hook warning fires.
            const overrideFn = convertWithSilencedWarning('originalComponent', {
                beforeUnmount() {},
                methods: { noop() {} },
            });

            const consoleWarn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            // Push AFTER mount → getCurrentInstance() is null → beforeUnmount cannot be registered
            _overridesMap.originalComponent.push(overrideFn);
            await flushPromises();

            expect(consoleWarn).not.toHaveBeenCalledWith(
                expect.stringContaining('[Options API Shim] Lifecycle hook "beforeUnmount" could not be registered'),
            );

            consoleWarn.mockRestore();
        });

        it('should invoke already-passed lifecycle hooks immediately when applied late (getCurrentInstance returns null)', async () => {
            // When the override is pushed after mount, beforeCreate/created/beforeMount/mounted
            // have already fired. The shim handles this by invoking those hooks synchronously
            // even though getCurrentInstance() is null at that point.
            const createdCallback = jest.fn();
            const mountedCallback = jest.fn();

            const originalComponent = defineComponent({
                template: '<div></div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        return { public: {} };
                    }),
            });

            mount(originalComponent);

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                created() {
                    createdCallback();
                },
                mounted() {
                    mountedCallback();
                },
                methods: { noop() {} },
            });

            // Push AFTER mount → both hooks are "already passed" → both must fire immediately
            _overridesMap.originalComponent.push(overrideFn);
            await flushPromises();

            expect(createdCallback).toHaveBeenCalledTimes(1);
            expect(mountedCallback).toHaveBeenCalledTimes(1);
        });
    });
});
