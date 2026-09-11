/**
 * @sw-package framework
 */

/* eslint-disable @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-argument */

import { createExtendableSetup, _overridesMap } from 'src/app/adapter/composition-extension-system';

import { mount } from '@vue/test-utils';
import { defineComponent, reactive } from 'vue';

import { convertWithSilencedWarning } from './fixtures';

describe('Options composition shim', () => {
    beforeEach(() => {
        const entries = [...Object.keys(_overridesMap)];
        entries.forEach((key) => {
            delete _overridesMap[key];
        });

        jest.clearAllMocks();
    });
    describe('inject resolution:', () => {
        it('should resolve array-form inject keys via this inside a lifecycle hook', async () => {
            const serviceInstance = { value: 'injected-value' };
            let capturedService: any = null;

            const originalComponent = defineComponent({
                template: '<div></div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        return { public: {} };
                    }),
            });

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                inject: ['myService'],
                created() {
                    capturedService = this.myService;
                },
            });

            // Push BEFORE mount so inject() runs inside the component's setup() context
            // (triggered by the immediate watch in createExtendableSetup).
            _overridesMap.originalComponent = reactive([]);
            _overridesMap.originalComponent.push(overrideFn);

            mount(originalComponent, {
                global: { provide: { myService: serviceInstance } },
            });

            await flushPromises();

            expect(capturedService).toBe(serviceInstance);
        });

        it('should resolve object-form inject with from/default fallback', async () => {
            let capturedVal: any;

            const originalComponent = defineComponent({
                template: '<div></div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        return { public: {} };
                    }),
            });

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                inject: { myVal: { from: 'nonExistentKey', default: 'fallback-value' } } as any,
                created() {
                    capturedVal = this.myVal;
                },
            });

            _overridesMap.originalComponent = reactive([]);
            _overridesMap.originalComponent.push(overrideFn);

            mount(originalComponent);

            await flushPromises();

            expect(capturedVal).toBe('fallback-value');
        });

        it('should include injected values in the legacy scope', async () => {
            let overrideResultKeys: string[] = [];

            const originalComponent = defineComponent({
                template: '<div></div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        return { public: {} };
                    }),
            });

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                inject: ['someService'],
                created() {
                    // capture what was actually returned in the result (not injectedValues)
                },
            });

            // Wrap the override fn to inspect its result
            const wrappedFn = (previousState: any, props: any, context?: any) => {
                const result = overrideFn(previousState, props, context);
                overrideResultKeys = Object.keys(result);
                return result;
            };

            _overridesMap.originalComponent = reactive([]);
            _overridesMap.originalComponent.push(wrappedFn);

            mount(originalComponent, {
                global: { provide: { someService: {} } },
            });

            await flushPromises();

            expect(overrideResultKeys).toContain('someService');
            expect(overrideResultKeys).not.toContain('_inject');
        });
    });
});
