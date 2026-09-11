/**
 * @sw-package framework
 */

/* eslint-disable @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-call, @typescript-eslint/no-explicit-any */

import { createExtendableSetup, _overridesMap } from 'src/app/adapter/composition-extension-system';

import { mount } from '@vue/test-utils';
import { defineComponent } from 'vue';

import { convertWithSilencedWarning } from './fixtures';

describe('Options composition shim', () => {
    beforeEach(() => {
        const entries = [...Object.keys(_overridesMap)];
        entries.forEach((key) => {
            delete _overridesMap[key];
        });

        jest.clearAllMocks();
    });
    describe('flattenMixins() — recursive mixin resolution:', () => {
        it('should resolve lifecycle hooks from deeply nested mixins', async () => {
            const callOrder: string[] = [];

            const deepMixin = {
                created() {
                    callOrder.push('deep-mixin');
                },
            };

            const shallowMixin = {
                mixins: [deepMixin],
                created() {
                    callOrder.push('shallow-mixin');
                },
            };

            const originalComponent = defineComponent({
                template: '<div></div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        return { public: {} };
                    }),
            });

            mount(originalComponent);

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                mixins: [shallowMixin],
                created() {
                    callOrder.push('component');
                },
            });

            _overridesMap.originalComponent.push(overrideFn);

            await flushPromises();

            // deep ancestor fires first, then shallow mixin, then component
            expect(callOrder).toEqual([
                'deep-mixin',
                'shallow-mixin',
                'component',
            ]);
        });

        it('should make methods from deeply nested mixins accessible via this', async () => {
            let capturedResult: string | null = null;

            const deepMixin = {
                methods: {
                    deepMethod() {
                        return 'from-deep-mixin';
                    },
                },
            };

            const shallowMixin = {
                mixins: [deepMixin],
            };

            const originalComponent = defineComponent({
                template: '<div></div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        return { public: {} };
                    }),
            });

            mount(originalComponent);

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                mixins: [shallowMixin],
                created() {
                    capturedResult = (this as any).deepMethod();
                },
            });

            _overridesMap.originalComponent.push(overrideFn);

            await flushPromises();

            expect(capturedResult).toBe('from-deep-mixin');
        });
    });
});
