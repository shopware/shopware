/**
 * @sw-package framework
 */

/* eslint-disable @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-call, @typescript-eslint/no-explicit-any */

import type { ComponentInternalInstance } from 'vue';
import { computed, defineComponent, getCurrentInstance, isReactive, isRef, onBeforeMount, ref } from 'vue';
import { mount } from '@vue/test-utils';
import { _overridesMap, getScriptSetupDataScope } from 'src/app/adapter/composition-extension-system';

const runtime = Shopware.Component.__setupRuntime.v1;
const name = 'sw-setup-runtime';

/** The shape the native setup transform emits for a base component. */
function defineLoweredBase(template = '<div>{{ count }}|{{ doubled }}</div>') {
    let instance: ComponentInternalInstance | null = null;

    const component = defineComponent({
        template,
        setup() {
            instance = getCurrentInstance();
            const __swSetupLate = runtime.late({
                // eslint-disable-next-line no-use-before-define
                count: () => __swSetupAuthor_count,
                // eslint-disable-next-line no-use-before-define
                increment: () => __swSetupAuthor_increment,
            }) as any;
            const __swSetupAuthor_count = ref(1);
            const __swSetupAuthor_doubled = computed(() => __swSetupLate.count.value * 2);
            const __swSetupAuthor_increment = () => {
                __swSetupLate.count.value += 1;
            };
            const __swSetupAuthor_click = () => {
                __swSetupLate.increment();
            };

            return runtime.attach({
                name,
                public: {
                    count: __swSetupAuthor_count,
                    doubled: __swSetupAuthor_doubled,
                    increment: __swSetupAuthor_increment,
                },
                private: { click: __swSetupAuthor_click },
                late: __swSetupLate,
            });
        },
    });

    return { component, getInstance: () => instance! };
}

describe('src/app/adapter/composition-extension-system: Shopware.Component.__setupRuntime.v1', () => {
    beforeEach(() => {
        _overridesMap.clear();
    });

    it('keeps the names that compiled SFCs used before the versioned interface', () => {
        expect(Object.isFrozen(runtime)).toBe(true);
        expect(Shopware.Component.attachOverrides).toBe(runtime.attach);
        expect(Shopware.Component.getExposedProps).toBe(runtime.expose);
        expect(Shopware.Component.registerOverrideComponent).toBe(runtime.registerComponent);
        expect(Shopware.Component.getOverrideComponents).toBe(runtime.getComponents);
    });

    describe('attach()', () => {
        it('applies an override registered at module scope', () => {
            runtime.override(name, 'override-file', (previousState: any) => ({
                doubled: computed(() => previousState.count.value * 3),
            }));

            expect(mount(defineLoweredBase().component).text()).toBe('1|3');
        });

        it('does not evaluate a computed during setup', () => {
            const evaluate = jest.fn(() => 2);

            mount(
                defineComponent({
                    template: '<div />',
                    setup() {
                        runtime.attach({ name, public: { doubled: computed(evaluate) } });

                        return {};
                    },
                }),
            );

            expect(evaluate).not.toHaveBeenCalled();
        });

        it('lets a computed depend on state that a lifecycle hook initializes', () => {
            const component = defineComponent({
                template: '<div>{{ label }}</div>',
                setup() {
                    const __swSetupAuthor_entity = ref<{ name: string } | null>(null);
                    const __swSetupAuthor_label = computed(() => __swSetupAuthor_entity.value!.name);

                    onBeforeMount(() => {
                        __swSetupAuthor_entity.value = { name: 'loaded' };
                    });

                    return runtime.attach({ name, public: { label: __swSetupAuthor_label } });
                },
            });

            expect(mount(component).text()).toBe('loaded');
        });

        it('registers the reactive state as data scope, including keys added after setup', () => {
            const { component, getInstance } = defineLoweredBase();
            mount(component);

            const dataScope = getScriptSetupDataScope(getInstance())!;
            expect(dataScope.count).toBe(1);
            expect(dataScope.doubled).toBe(2);

            dataScope.added = 'later';

            expect(getScriptSetupDataScope(getInstance())!.added).toBe('later');
        });

        it('merges the override-local state of every override file into the non-enumerable __swOverride', () => {
            const first = Symbol('first');
            const second = Symbol('second');
            runtime.override(name, 'first-file', () => ({ __swOverride: { [first]: { message: ref('one') } } }));
            runtime.override(name, 'second-file', () => ({ __swOverride: { [second]: { message: 'two' } } }));

            const { component, getInstance } = defineLoweredBase();
            mount(component);
            const dataScope = getScriptSetupDataScope(getInstance())!;
            const overrideLocalState = dataScope.__swOverride as Record<symbol, Record<string, unknown>>;

            expect(Object.keys(dataScope)).not.toContain('__swOverride');
            expect(overrideLocalState[first].message).toBe('one');
            expect(overrideLocalState[second].message).toBe('two');
        });
    });

    describe('override()', () => {
        it('replaces the entry of a file key in place', () => {
            runtime.override(name, 'first-file', () => ({ count: ref(10) }));
            runtime.override(name, 'second-file', (previousState: any) => ({
                count: ref(previousState.count.value + 1),
            }));
            runtime.override(name, 'first-file', () => ({ count: ref(20) }));

            expect(_overridesMap.get(name)?.size).toBe(2);
            expect(mount(defineLoweredBase().component).text()).toBe('21|42');
        });
    });

    describe('late()', () => {
        it('returns the fallbacks until attach() binds it', () => {
            const late = runtime.late({ count: () => 'fallback' }) as any;

            expect(late.count).toBe('fallback');

            const count = ref(1);
            runtime.attach({ name, public: { count }, late });

            expect(late.count).toBe(count);
        });

        it('lets the base body call an overridden function', async () => {
            runtime.override(name, 'override-file', (previousState: any) => ({
                increment: () => {
                    previousState.count.value += 10;
                },
            }));

            const wrapper = mount(defineLoweredBase('<button @click="click">{{ count }}</button>').component);
            await wrapper.get('button').trigger('click');

            expect(wrapper.text()).toBe('11');
        });

        it('lets a base computed derive from an overridden binding', () => {
            runtime.override(name, 'override-file', () => ({ count: computed(() => 7) }));

            expect(mount(defineLoweredBase().component).text()).toBe('7|14');
        });
    });

    describe('expose()', () => {
        it('exposes every prop as a read-only ref that follows the prop', async () => {
            let exposed: Record<string, any> = {};
            const component = defineComponent({
                template: '<div />',
                props: { label: String },
                setup() {
                    exposed = runtime.expose();

                    return {};
                },
            });

            const wrapper = mount(component, { props: { label: 'first' } });
            expect(isRef(exposed.label)).toBe(true);
            expect(exposed.label.value).toBe('first');

            await wrapper.setProps({ label: 'second' });
            expect(exposed.label.value).toBe('second');

            const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});
            exposed.label.value = 'written';
            expect(exposed.label.value).toBe('second');
            expect(warn).toHaveBeenCalledWith(
                'The prop "label" is exposed read-only. Pass a new value from the parent instead.',
            );
            warn.mockRestore();
        });
    });

    describe('registerComponent()', () => {
        it('registers a component once and does not make it reactive', () => {
            const component = defineComponent({ template: '<div />' });
            const before = runtime.getComponents().length;

            runtime.registerComponent(component);
            runtime.registerComponent(component);

            expect(runtime.getComponents()).toHaveLength(before + 1);
            expect(runtime.getComponents().at(-1)).toBe(component);
            expect(isReactive(runtime.getComponents().at(-1))).toBe(false);
        });
    });
});
