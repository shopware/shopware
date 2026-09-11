/**
 * @sw-package framework
 */

/* eslint-disable @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-call, @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-return */

import { createExtendableSetup, _overridesMap } from 'src/app/adapter/composition-extension-system';
import { convertOptionsApiOverrideToCompositionApi } from 'src/app/adapter/options-composition-shim';

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
    describe('Edge cases:', () => {
        it('should handle override with only data and no existing methods', async () => {
            const originalComponent = defineComponent({
                template: '<div class="name">Name: {{ name }}</div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const name = ref('original');

                        return {
                            public: { name },
                        };
                    }),
            });

            const wrapper = mount(originalComponent);
            expect(wrapper.find('.name').text()).toBe('Name: original');

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                data() {
                    return { name: 'overridden' };
                },
            });

            _overridesMap.originalComponent.push(overrideFn);

            await flushPromises();

            expect(wrapper.find('.name').text()).toBe('Name: overridden');
        });

        it('should handle empty data function', () => {
            const overrideFn = convertWithSilencedWarning('originalComponent', {
                data() {
                    return {};
                },
            });

            const result = overrideFn({}, {});

            expect(Object.keys(result)).toHaveLength(0);
        });

        it('should handle null/undefined data gracefully', () => {
            const overrideFn = convertWithSilencedWarning('originalComponent', {
                data() {
                    return null as any;
                },
            });

            const result = overrideFn({}, {});
            expect(result).toBeDefined();
        });

        it('should handle override with only computed, no methods or data', async () => {
            const originalComponent = defineComponent({
                template: '<div class="display">{{ display }}</div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const count = ref(5);
                        const display = computed(() => `${count.value}`);

                        return {
                            public: { count, display },
                        };
                    }),
            });

            const wrapper = mount(originalComponent);
            expect(wrapper.find('.display').text()).toBe('5');

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                computed: {
                    display() {
                        return `Modified: ${this.count}`;
                    },
                },
            });

            _overridesMap.originalComponent.push(overrideFn);

            await flushPromises();

            expect(wrapper.find('.display').text()).toBe('Modified: 5');
        });

        it('should expose injections to the legacy template scope', () => {
            // Suppress all warnings (deprecation + Vue "inject outside setup" since we call
            // the override function directly in this unit test, outside a component context).
            const consoleWarn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            const overrideFn = convertOptionsApiOverrideToCompositionApi('originalComponent', {
                inject: [
                    'repositoryFactory',
                    'acl',
                ],
                methods: { foo() {} },
            });

            const result = overrideFn({}, {});

            // Templates and methods share the same injected values.
            expect(result._inject).toBeUndefined();
            expect(Object.keys(result)).toContain('repositoryFactory');
            expect(Object.keys(result)).toContain('acl');

            consoleWarn.mockRestore();
        });

        it('should handle config with no Options API patterns gracefully', () => {
            const overrideFn = convertWithSilencedWarning('originalComponent', {});

            const result = overrideFn({}, {});

            expect(result).toBeDefined();
            expect(typeof result).toBe('object');
        });

        it('should handle methods that return values', () => {
            const previousState = {
                count: ref(10),
            };

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                methods: {
                    getDoubledCount() {
                        return this.count * 2;
                    },
                },
            });

            const result = overrideFn(previousState, {}) as Record<string, any>;

            expect(result.getDoubledCount()).toBe(20);
        });

        it('should handle methods with arguments', () => {
            const previousState = {
                count: ref(0),
            };

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                methods: {
                    addToCount(amount: number) {
                        this.count += amount;
                    },
                },
            });

            const result = overrideFn(previousState, {}) as Record<string, any>;
            result.addToCount(42);

            expect(previousState.count.value).toBe(42);
        });
    });
});
