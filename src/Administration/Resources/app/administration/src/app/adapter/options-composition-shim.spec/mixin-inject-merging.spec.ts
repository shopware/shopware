/**
 * @sw-package framework
 */

/* eslint-disable @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-call, @typescript-eslint/no-explicit-any */

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
    describe('Mixin inject merging:', () => {
        it('should resolve inject from mixin via this in a method', async () => {
            const serviceInstance = { load: () => 'loaded' };
            let capturedService: any = null;

            const myMixin = {
                inject: ['repositoryFactory'] as any,
            };

            const originalComponent = defineComponent({
                template: '<div></div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        return { public: {} };
                    }),
            });

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                mixins: [myMixin],
                created() {
                    capturedService = this.repositoryFactory;
                },
            });

            _overridesMap.originalComponent = reactive([]);
            _overridesMap.originalComponent.push(overrideFn);

            mount(originalComponent, {
                global: { provide: { repositoryFactory: serviceInstance } },
            });

            await flushPromises();

            expect(capturedService).toBe(serviceInstance);
        });

        it('should resolve inject from deeply nested mixin', async () => {
            let capturedAcl: any = null;

            const deepMixin = {
                inject: ['acl'] as any,
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

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                mixins: [shallowMixin],
                created() {
                    capturedAcl = this.acl;
                },
            });

            _overridesMap.originalComponent = reactive([]);
            _overridesMap.originalComponent.push(overrideFn);

            mount(originalComponent, {
                global: { provide: { acl: { can: () => true } } },
            });

            await flushPromises();

            expect(capturedAcl).toBeDefined();
            expect(capturedAcl.can()).toBe(true);
        });

        it('should let component inject win over mixin inject on conflict', async () => {
            let capturedVal: any = null;

            const myMixin = {
                inject: { myService: { from: 'myService', default: 'mixin-default' } } as any,
            };

            const originalComponent = defineComponent({
                template: '<div></div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        return { public: {} };
                    }),
            });

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                mixins: [myMixin],
                inject: { myService: { from: 'myService', default: 'component-default' } } as any,
                created() {
                    capturedVal = this.myService;
                },
            });

            _overridesMap.originalComponent = reactive([]);
            _overridesMap.originalComponent.push(overrideFn);

            mount(originalComponent);

            await flushPromises();

            expect(capturedVal).toBe('component-default');
        });
    });
});
