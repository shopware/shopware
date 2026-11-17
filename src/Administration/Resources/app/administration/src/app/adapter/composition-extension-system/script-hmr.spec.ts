/**
 * @sw-package framework
 */
import { flushPromises, mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { computed, defineComponent, h, ref } from 'vue';
import type { Component, ComputedRef } from 'vue';
import type { ComponentConfig, SetupHmrMetadata } from 'src/core/factory/async-component.factory';
import { _overridesMap, createExtendableSetup, overrideComponentSetup } from './index';
import { attachSetupOverrideShim } from './options-api-setup-shim';
import { convertOptionsApiOverrideToCompositionApi } from '../options-composition-shim';

type HmrRuntime = { reload: (id: string, component: unknown) => void };

const runtime = (globalThis as typeof globalThis & { __VUE_HMR_RUNTIME__: HmrRuntime }).__VUE_HMR_RUNTIME__;

type Total = { total: ComputedRef<number> };

let sequence = 0;

/** What a compiled `.override.vue` is: a hidden component whose setup registers the override callback. */
function overrideSfc(name: string, owner: string, version: string, add: number, data: SetupHmrMetadata['data'] = {}) {
    return Object.assign(
        defineComponent({
            setup() {
                overrideComponentSetup()(
                    name as never,
                    ((previous: Total) => ({
                        total: computed(() => previous.total.value + add),
                    })) as never,
                );

                return () => null;
            },
        }),
        {
            __hmrId: owner,
            __shopwareSetupHmr: { componentName: name, mode: 'override', version, data } satisfies SetupHmrMetadata,
        },
    );
}

function nativeBase(name: string): Component {
    return Object.assign(
        defineComponent({
            template: '<section><input v-model="draft"><p>{{ total }}</p></section>',
            setup(props, context) {
                return createExtendableSetup({ name: name as never, props, context }, () => ({
                    public: { total: computed(() => 10), draft: ref('initial') } as never,
                }));
            },
        }),
        { __hmrId: `${name}:base` },
    );
}

function optionsApiBase(name: string): Component {
    const config = {
        __hmrId: `${name}:base`,
        template: '<section><input v-model="draft"><p>{{ total }}</p></section>',
        data: () => ({ draft: 'initial', total: 10 }),
    } as unknown as ComponentConfig;
    attachSetupOverrideShim(name, config);

    return config as Component;
}

describe('src/app/adapter/composition-extension-system/script-hmr', () => {
    const wrappers: VueWrapper[] = [];
    let name: string;

    function mountAll(...children: Component[]) {
        const parentDraft = ref('parent');
        const wrapper = mount(
            defineComponent({
                setup: () => () =>
                    h('main', [
                        h('input', {
                            'data-parent': '',
                            value: parentDraft.value,
                            onInput: (event: Event) => {
                                parentDraft.value = (event.target as HTMLInputElement).value;
                            },
                        }),
                        ...children.map((child) => h(child)),
                    ]),
            }),
        );
        wrappers.push(wrapper);

        return wrapper;
    }

    const totals = (wrapper: VueWrapper) => wrapper.findAll('p').map((paragraph) => paragraph.text());

    beforeEach(() => {
        sequence += 1;
        name = `sw-script-hmr-${sequence}`;
    });

    afterEach(() => {
        wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
        delete _overridesMap[name];
    });

    it.each([
        ['native', nativeBase],
        ['Options API', optionsApiBase],
    ])(
        'replaces an edited override on a %s base, resets the base and keeps its parent',
        async (_label: string, base: (name: string) => Component) => {
            const owner = `${name}:override`;
            const Base = base(name);
            const wrapper = mountAll(overrideSfc(name, owner, '1', 1), Base, Base);
            await flushPromises();
            await wrapper.find('[data-parent]').setValue('unsaved parent');
            await wrapper.find('section input').setValue('unsaved child');
            expect(totals(wrapper)).toEqual(['11', '11']);

            for (let edit = 2; edit <= 5; edit += 1) {
                runtime.reload(owner, overrideSfc(name, owner, String(edit), edit));

                await flushPromises();
            }

            expect(totals(wrapper)).toEqual(['15', '15']);
            expect(_overridesMap[name]).toHaveLength(1);
            expect(wrapper.findAll<HTMLInputElement>('section input').map((input) => input.element.value)).toEqual([
                'initial',
                'initial',
            ]);
            expect(wrapper.find<HTMLInputElement>('[data-parent]').element.value).toBe('unsaved parent');
        },
    );

    it('applies an override that registers after its base mounted', async () => {
        const Base = nativeBase(name);
        const showOverride = ref(false);
        const Override = overrideSfc(name, `${name}:override`, '1', 1);
        const wrapper = mountAll(Base, defineComponent({ setup: () => () => (showOverride.value ? h(Override) : null) }));
        await flushPromises();
        expect(totals(wrapper)).toEqual(['10']);

        showOverride.value = true;
        await flushPromises();

        expect(totals(wrapper)).toEqual(['11']);
    });

    it('keeps the position of an edited override between the overrides of other extensions', async () => {
        const owner = `${name}:second`;
        const wrapper = mountAll(
            overrideSfc(name, `${name}:first`, '1', 1),
            overrideSfc(name, owner, '1', 100),
            defineComponent({
                setup() {
                    overrideComponentSetup()(
                        name as never,
                        ((previous: Total) => ({
                            total: computed(() => previous.total.value * 2),
                        })) as never,
                    );

                    return () => null;
                },
            }),
            nativeBase(name),
        );
        await flushPromises();
        expect(totals(wrapper)).toEqual(['222']);

        runtime.reload(owner, overrideSfc(name, owner, '2', 1000));
        await flushPromises();

        expect(totals(wrapper)).toEqual(['2022']);
    });

    it('does not register an override twice when it mounts again without a script change', async () => {
        const owner = `${name}:override`;
        const show = ref(true);
        const Override = overrideSfc(name, owner, '1', 1);
        const wrapper = mountAll(
            defineComponent({ setup: () => () => (show.value ? h(Override) : null) }),
            nativeBase(name),
        );
        await flushPromises();

        show.value = false;
        await flushPromises();
        show.value = true;
        await flushPromises();

        expect(totals(wrapper)).toEqual(['11']);
        expect(_overridesMap[name]).toHaveLength(1);
    });

    it('gives an edited override that destructures its previous state the fresh base state', async () => {
        const owner = `${name}:override`;
        const destructuring = (add: number) =>
            Object.assign(
                defineComponent({
                    setup() {
                        overrideComponentSetup()(
                            name as never,
                            (({ total }: Total) => ({
                                total: computed(() => total.value + add),
                            })) as never,
                        );

                        return () => null;
                    },
                }),
                {
                    __hmrId: owner,
                    __shopwareSetupHmr: {
                        componentName: name,
                        mode: 'override',
                        version: String(add),
                        data: {},
                    } satisfies SetupHmrMetadata,
                },
            );
        const showBase = ref(false);
        const Base = nativeBase(name);
        const wrapper = mountAll(
            destructuring(1),
            defineComponent({ setup: () => () => (showBase.value ? h(Base) : null) }),
        );
        await flushPromises();
        showBase.value = true;
        await flushPromises();
        expect(totals(wrapper)).toEqual(['11']);

        runtime.reload(owner, destructuring(2));
        await flushPromises();

        expect(totals(wrapper)).toEqual(['12']);
    });

    it('applies an Options API override converted after an override was edited', async () => {
        const owner = `${name}:override`;
        const wrapper = mountAll(overrideSfc(name, owner, '1', 1), nativeBase(name));
        await flushPromises();
        runtime.reload(owner, overrideSfc(name, owner, '2', 2));
        await flushPromises();

        jest.spyOn(console, 'warn').mockImplementationOnce(() => {});
        _overridesMap[name].push(
            convertOptionsApiOverrideToCompositionApi(name, {
                computed: {
                    total(this: { $super: (name: string) => number }): number {
                        return this.$super('total') + 100;
                    },
                },
            }),
        );
        await flushPromises();

        expect(totals(wrapper)).toEqual(['112']);
    });

    it('applies a Component.override() of the base once after an override was edited', async () => {
        const owner = `${name}:override`;
        Shopware.Component.override(name, {
            computed: {
                total(this: { $super: (name: string) => number }): number {
                    return this.$super('total') + 100;
                },
            },
        });
        jest.spyOn(console, 'warn').mockImplementation(() => {});
        const wrapper = mountAll(overrideSfc(name, owner, '1', 1), nativeBase(name));
        await flushPromises();
        expect(totals(wrapper)).toEqual(['111']);

        runtime.reload(owner, overrideSfc(name, owner, '2', 2));
        await flushPromises();

        expect(totals(wrapper)).toEqual(['112']);
    });

    it('adds every registration of an override without hot update data', async () => {
        const show = ref(true);
        const Override = defineComponent({
            setup() {
                overrideComponentSetup()(
                    name as never,
                    ((previous: Total) => ({
                        total: computed(() => previous.total.value + 1),
                    })) as never,
                );

                return () => null;
            },
        });
        mountAll(defineComponent({ setup: () => () => (show.value ? h(Override) : null) }));
        await flushPromises();

        show.value = false;
        await flushPromises();
        show.value = true;
        await flushPromises();

        expect(_overridesMap[name]).toHaveLength(2);
    });
});
