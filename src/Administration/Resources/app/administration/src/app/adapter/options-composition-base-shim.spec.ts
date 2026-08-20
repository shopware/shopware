/**
 * @sw-package framework
 */

/**
 * Options API bodies read their own state off `this`, which the compatibility proxy types as `any` by
 * design - the same relaxation the sibling override spec applies.
 */
/* eslint-disable @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-explicit-any */

/**
 * Covers the setup channel of the Native → Twig Extension Bridge: turning an Options API base component
 * into one whose state runs through `createExtendableSetup()`, so native `.override.vue` setup
 * extensions apply to a component that was never migrated.
 */

import { mount } from '@vue/test-utils';
import { computed } from 'vue';
import { _overridesMap, getScriptSetupDataScope } from 'src/app/adapter/composition-extension-system';
import {
    convertOptionsApiBaseToExtendableSetup,
    getOptionsApiBaseConversionBlocker,
} from 'src/app/adapter/options-composition-shim';
import type { ComponentConfig } from 'src/core/factory/async-component.factory';

function convert(componentName: string, config: ComponentConfig): ComponentConfig {
    return convertOptionsApiBaseToExtendableSetup(componentName, config);
}

describe('src/app/adapter/options-composition-shim — base conversion', () => {
    beforeEach(() => {
        Object.keys(_overridesMap).forEach((key) => {
            delete _overridesMap[key];
        });
    });

    describe('getOptionsApiBaseConversionBlocker():', () => {
        it('reports no blocker for a plain Options API component', () => {
            expect(getOptionsApiBaseConversionBlocker({ data: () => ({}) })).toBeNull();
        });

        it('blocks a component with a custom render function', () => {
            expect(getOptionsApiBaseConversionBlocker({ render: () => null })).toContain('render()');
        });

        it('blocks a component with a resolved extends chain', () => {
            expect(getOptionsApiBaseConversionBlocker({ extends: { name: 'sw-base' } })).toContain('extends');
        });
    });

    describe('converted state:', () => {
        it('exposes data, computed and methods to the template', async () => {
            const wrapper = mount(
                convert('sw-legacy', {
                    template: '<button class="value" @click="increase">{{ doubled }}</button>',
                    data() {
                        return { count: 2 };
                    },
                    computed: {
                        doubled() {
                            return this.count * 2;
                        },
                    },
                    methods: {
                        increase() {
                            this.count += 1;
                        },
                    },
                }),
            );

            expect(wrapper.get('.value').text()).toBe('4');

            await wrapper.get('.value').trigger('click');

            expect(wrapper.get('.value').text()).toBe('6');
        });

        it('calls data() with a receiver that resolves props', () => {
            const wrapper = mount(
                convert('sw-legacy', {
                    template: '<div class="value">{{ label }}</div>',
                    props: {
                        initialLabel: {
                            type: String,
                            default: 'fallback',
                        },
                    },
                    data() {
                        return { label: this.initialLabel };
                    },
                }),
                {
                    props: { initialLabel: 'from-prop' },
                },
            );

            expect(wrapper.get('.value').text()).toBe('from-prop');
        });

        it('runs the created hook and keeps writes visible in the template', () => {
            const wrapper = mount(
                convert('sw-legacy', {
                    template: '<div class="value">{{ label }}</div>',
                    data() {
                        return { label: 'before' };
                    },
                    created() {
                        this.label = 'after';
                    },
                }),
            );

            expect(wrapper.get('.value').text()).toBe('after');
        });

        it('registers mounted and unmounted hooks', () => {
            const mounted = jest.fn();
            const unmounted = jest.fn();

            const wrapper = mount(
                convert('sw-legacy', {
                    template: '<div></div>',
                    mounted,
                    unmounted,
                }),
            );

            expect(mounted).toHaveBeenCalledTimes(1);

            wrapper.unmount();

            expect(unmounted).toHaveBeenCalledTimes(1);
        });

        it('registers watchers, including dot-notation paths', async () => {
            const flatWatcher = jest.fn();
            const pathWatcher = jest.fn();

            const wrapper = mount(
                convert('sw-legacy', {
                    template: '<button class="trigger" @click="update"></button>',
                    data() {
                        return {
                            count: 0,
                            user: { name: 'initial' },
                        };
                    },
                    watch: {
                        count: flatWatcher,
                        'user.name': pathWatcher,
                    },
                    methods: {
                        update() {
                            this.count += 1;
                            this.user = { name: 'changed' };
                        },
                    },
                }),
            );

            await wrapper.get('.trigger').trigger('click');

            expect(flatWatcher).toHaveBeenCalledWith(1, 0);
            expect(pathWatcher).toHaveBeenCalledWith('changed', 'initial');
        });

        it('resolves inject for the template and for converted methods', () => {
            const wrapper = mount({
                template: '<sw-legacy />',
                provide: { greeting: 'hello' },
                components: {
                    'sw-legacy': convert('sw-legacy', {
                        template: '<div class="value">{{ greeting }} / {{ shouted }}</div>',
                        inject: ['greeting'],
                        computed: {
                            shouted() {
                                return (this.greeting as string).toUpperCase();
                            },
                        },
                    }),
                },
            });

            expect(wrapper.get('.value').text()).toBe('hello / HELLO');
        });

        it('keeps provide, components and inheritAttrs on the converted config', () => {
            const config = convert('sw-legacy', {
                template: '<div></div>',
                inheritAttrs: false,
                provide() {
                    return { fromBase: true };
                },
                components: { 'sw-child': { template: '<span></span>' } },
                data: () => ({ count: 1 }),
            });

            expect(config.inheritAttrs).toBe(false);
            expect(config.provide).toBeDefined();
            expect(config.components).toBeDefined();
            expect(config.data).toBeUndefined();
            expect(typeof config.setup).toBe('function');
        });
    });

    describe('mixins:', () => {
        it('folds mixin data, computed and methods into the setup state', () => {
            const wrapper = mount(
                convert('sw-legacy', {
                    template: '<div class="value">{{ mixinComputed }}</div>',
                    mixins: [
                        {
                            data() {
                                return { fromMixin: 'mixin' };
                            },
                            computed: {
                                mixinComputed(this: { fromMixin: string; fromComponent: string }): string {
                                    return `${this.fromMixin}-${this.fromComponent}`;
                                },
                            },
                        },
                    ],
                    data() {
                        return { fromComponent: 'component' };
                    },
                }),
            );

            expect(wrapper.get('.value').text()).toBe('mixin-component');
        });

        it('runs a mixin lifecycle hook exactly once', () => {
            const created = jest.fn();

            mount(
                convert('sw-legacy', {
                    template: '<div></div>',
                    mixins: [{ created }],
                }),
            );

            expect(created).toHaveBeenCalledTimes(1);
        });

        it('resolves mixins referenced by name', () => {
            // The registry is keyed by the generated MixinContainer union, which a spec-only mixin is not
            // part of; the runtime lookup only ever sees the string.
            Shopware.Mixin.register('sw-native-bridge-spec-mixin' as keyof MixinContainer, {
                data() {
                    return { fromNamedMixin: 'named' };
                },
            });

            const wrapper = mount(
                convert('sw-legacy', {
                    template: '<div class="value">{{ fromNamedMixin }}</div>',
                    mixins: ['sw-native-bridge-spec-mixin'],
                }),
            );

            expect(wrapper.get('.value').text()).toBe('named');
        });

        it('keeps mixin props on the converted component', () => {
            const wrapper = mount(
                convert('sw-legacy', {
                    template: '<div class="value">{{ title }}</div>',
                    mixins: [
                        {
                            props: {
                                title: {
                                    type: String,
                                    default: 'default-title',
                                },
                            },
                        },
                    ],
                }),
                {
                    props: { title: 'from-mixin-prop' },
                },
            );

            expect(wrapper.get('.value').text()).toBe('from-mixin-prop');
        });
    });

    describe('extension surface:', () => {
        it('applies a native setup override to the converted base', () => {
            Shopware.Component.overrideComponentSetup()('sw-legacy', (previousState: any) => ({
                label: computed(() => `${previousState.label.value}!`),
            }));

            const wrapper = mount(
                convert('sw-legacy', {
                    template: '<div class="value">{{ label }}</div>',
                    data() {
                        return { label: 'base' };
                    },
                }),
            );

            expect(wrapper.get('.value').text()).toBe('base!');
        });

        it('lets a native setup override replace a converted method', async () => {
            const wrapper = mount(
                convert('sw-legacy', {
                    template: '<button class="value" @click="increase">{{ count }}</button>',
                    data() {
                        return { count: 0 };
                    },
                    methods: {
                        increase() {
                            this.count += 1;
                        },
                    },
                }),
            );

            Shopware.Component.overrideComponentSetup()('sw-legacy', (previousState: any) => ({
                increase: () => {
                    previousState.count.value += 10;
                },
            }));

            await flushPromises();
            await wrapper.get('.value').trigger('click');

            expect(wrapper.get('.value').text()).toBe('10');
        });

        it('registers a block data scope so sw-native-block-host can read the converted state', () => {
            const wrapper = mount(
                convert('sw-legacy', {
                    template: '<div class="value">{{ label }}</div>',
                    data() {
                        return { label: 'scoped' };
                    },
                }),
            );

            const dataScope = getScriptSetupDataScope(wrapper.vm.$) as Record<string, unknown> | null;

            expect(dataScope).not.toBeNull();
            expect(dataScope?.label).toBe('scoped');
        });
    });
});
