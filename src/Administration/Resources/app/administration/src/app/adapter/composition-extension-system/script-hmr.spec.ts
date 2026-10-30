/**
 * @sw-package framework
 */
import { mount, flushPromises } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { computed, defineComponent, h, nextTick, ref, watch } from 'vue';
import type { App, ComponentOptions } from 'vue';
import type { ComponentConfig } from 'src/core/factory/async-component.factory';
import type { OverrideFn } from '../options-composition-shim';
import { _overridesMap, createExtendableSetup, overrideComponentSetup } from './index';
import { attachSetupOverrideShim } from './options-api-setup-shim';
import * as ScriptHmr from './script-hmr';

jest.mock('./script-hmr', () => {
    const actual = jest.requireActual<typeof ScriptHmr>('./script-hmr');
    return {
        ...actual,
        trackSetupHmrComponent: jest.fn(),
        registerSetupOverrideForHmr: jest.fn(),
        invalidateFailedSetupHmr: jest.fn(),
    };
});

type HmrRuntime = {
    createRecord: (id: string, component: unknown) => boolean;
    reload: (id: string, component: unknown) => void;
};
const runtime = (globalThis as typeof globalThis & { __VUE_HMR_RUNTIME__: HmrRuntime }).__VUE_HMR_RUNTIME__;
let sequence = 0;
const ownerData = new Map<string, { refreshParent?: () => void }>();

/** Mounts the same hidden registration boundary emitted by the native SFC compiler. */
function registration(name: string, owner: string, version: string, callback: OverrideFn, invalidate = jest.fn()) {
    const data = ownerData.get(owner) ?? {};
    ownerData.set(owner, data);
    return Object.assign(
        defineComponent({
            setup() {
                overrideComponentSetup()(name, callback as never);
                return () => null;
            },
        }),
        {
            __hmrId: owner,
            __shopwareSetupHmr: { componentName: name, mode: 'override', version, invalidate, data },
        },
    );
}

function base(name: string, optionsApi = false) {
    if (optionsApi) {
        const config = {
            __hmrId: `${name}:base`,
            template: '<section><input v-model="draft"><p>{{ total }}</p></section>',
            data: () => ({ draft: 'initial', total: 10 }),
        } as unknown as ComponentConfig;
        attachSetupOverrideShim(name, config);
        return config as unknown as ComponentOptions;
    }
    return Object.assign(
        defineComponent({
            template: '<section><input v-model="draft"><p>{{ total }}</p></section>',
            setup(props, context) {
                return createExtendableSetup({ name, props, context }, () => ({
                    public: { total: computed(() => 10), draft: ref('initial') },
                }));
            },
        }),
        { __hmrId: `${name}:base` },
    );
}

function add(delta: number): OverrideFn {
    return ((previous: { total: { value: number } }) => ({
        total: computed(() => previous.total.value + delta),
    })) as unknown as OverrideFn;
}

describe('native component script HMR', () => {
    const wrappers: VueWrapper[] = [];
    let name: string;
    beforeEach(() => {
        name = `sw-script-hmr-${++sequence}`;
        const actual = jest.requireActual<typeof ScriptHmr>('./script-hmr');
        jest.mocked(ScriptHmr.trackSetupHmrComponent).mockImplementation(actual.trackSetupHmrComponent);
        jest.mocked(ScriptHmr.registerSetupOverrideForHmr).mockImplementation(actual.registerSetupOverrideForHmr);
        jest.mocked(ScriptHmr.invalidateFailedSetupHmr).mockImplementation(actual.invalidateFailedSetupHmr);
    });
    afterEach(() => {
        wrappers.reverse().forEach((wrapper) => wrapper.unmount());
        wrappers.length = 0;
        delete _overridesMap[name];
    });

    it.activeFeatureFlags(['ADMIN_VITE_8']).each([false, true])(
        'replaces a computed in order and remounts both instances without resetting their parent (Options API: %s)',
        async (optionsApi) => {
            const owner = `${name}:override`;
            const Override = registration(name, owner, '1', add(1));
            const Base = base(name, optionsApi);
            runtime.createRecord(owner, Override);
            const parentDraft = ref('parent initial');
            const sharedState = ref('store initial');
            const Parent = defineComponent({
                setup: () => () =>
                    h('main', [
                        h('input', {
                            'data-parent': '',
                            value: parentDraft.value,
                            onInput: (event: Event) => {
                                parentDraft.value = (event.target as HTMLInputElement).value;
                            },
                        }),
                        h('span', { 'data-store': '' }, sharedState.value),
                        h(Override),
                        h(Base),
                        h(Base),
                    ]),
            });
            const wrapper = mount(Parent);
            wrappers.push(wrapper);
            await flushPromises();
            await wrapper.find('[data-parent]').setValue('unsaved parent');
            await wrapper.find('section input').setValue('unsaved child');
            sharedState.value = 'unsaved store';
            expect(wrapper.findAll('p').map((item) => item.text())).toEqual(['11', '11']);
            runtime.reload(owner, registration(name, owner, '2', add(2)));
            await flushPromises();
            expect(wrapper.findAll('p').map((item) => item.text())).toEqual(['12', '12']);
            expect(wrapper.findAll('section input').map((item) => (item.element as HTMLInputElement).value)).toEqual([
                'initial',
                'initial',
            ]);
            expect((wrapper.find('[data-parent]').element as HTMLInputElement).value).toBe('unsaved parent');
            expect(wrapper.find('[data-store]').text()).toBe('unsaved store');
            expect(_overridesMap[name]).toHaveLength(1);
        },
    );

    it.activeFeatureFlags(['ADMIN_VITE_8'])(
        'keeps three override positions stable through twenty edits and disposes old watchers',
        async () => {
            const signal = ref(0);
            const effects: jest.Mock[] = [];
            const watchedOverride = (delta: number): OverrideFn => {
                const effect = jest.fn();
                effects.push(effect);
                return ((previous: { total: { value: number } }) => {
                    watch(signal, effect);
                    return { total: computed(() => previous.total.value + delta) };
                }) as unknown as OverrideFn;
            };
            const owner = `${name}:middle`;
            const Middle = registration(name, owner, '0', watchedOverride(2));
            const First = registration(name, `${name}:first`, '1', add(1));
            const Last = registration(name, `${name}:last`, '1', add(3));
            const Base = base(name);
            runtime.createRecord(owner, Middle);
            const wrapper = mount(
                defineComponent({
                    render: () =>
                        h('main', [
                            h(First),
                            h(Middle),
                            h(Last),
                            h(Base),
                        ]),
                }),
            );
            wrappers.push(wrapper);
            await flushPromises();
            expect(wrapper.find('p').text()).toBe('16');
            const first = _overridesMap[name][0];
            const last = _overridesMap[name][2];
            for (let edit = 1; edit <= 20; edit += 1) {
                runtime.reload(owner, registration(name, owner, String(edit), watchedOverride(edit + 2)));
                await flushPromises();
                expect(wrapper.find('p').text()).toBe(String(16 + edit));
                expect(_overridesMap[name]).toHaveLength(3);
                expect(_overridesMap[name][0]).toBe(first);
                expect(_overridesMap[name][2]).toBe(last);
                effects.forEach((effect) => effect.mockClear());
                signal.value += 1;
                await nextTick();
                expect(effects.slice(0, -1).every((effect) => effect.mock.calls.length === 0)).toBe(true);
                expect(effects.at(-1)).toHaveBeenCalledTimes(1);
            }
        },
    );

    it.activeFeatureFlags(['ADMIN_VITE_8'])(
        'does not remount the base when an unchanged script registration remounts',
        async () => {
            const owner = `${name}:override`;
            const Override = registration(name, owner, 'same-script', add(1));
            runtime.createRecord(owner, Override);
            const Base = base(name);
            const wrapper = mount(defineComponent({ render: () => h('main', [h(Override), h(Base)]) }));
            wrappers.push(wrapper);
            await flushPromises();
            await wrapper.find('input').setValue('draft');
            runtime.reload(owner, registration(name, owner, 'same-script', add(1)));
            await flushPromises();
            expect(wrapper.find('p').text()).toBe('11');
            expect((wrapper.find('input').element as HTMLInputElement).value).toBe('draft');
            expect(_overridesMap[name]).toHaveLength(1);
        },
    );

    it.activeFeatureFlags(['ADMIN_VITE_8'])('replaces an override before its target is mounted', async () => {
        const owner = `${name}:override`;
        const Override = registration(name, owner, '1', add(1));
        runtime.createRecord(owner, Override);
        wrappers.push(mount(defineComponent({ render: () => h(Override) })));
        runtime.reload(owner, registration(name, owner, '2', add(2)));
        await flushPromises();
        const wrapper = mount(base(name) as never);
        wrappers.push(wrapper);
        expect(wrapper.find('p').text()).toBe('12');
        expect(_overridesMap[name]).toHaveLength(1);
    });

    it.activeFeatureFlags(['ADMIN_VITE_8']).each([false, true])(
        'invalidates the component boundary when replacement setup throws (Options API: %s)',
        async (optionsApi) => {
            const owner = `${name}:override`;
            const invalidate = jest.fn();
            const Override = registration(name, owner, '1', add(1), invalidate);
            runtime.createRecord(owner, Override);
            const Base = base(name, optionsApi);
            const wrapper = mount(defineComponent({ render: () => h('main', [h(Override), h(Base)]) }), {
                global: { config: { errorHandler: jest.fn() } },
            });
            wrappers.push(wrapper);
            await flushPromises();
            runtime.reload(
                owner,
                registration(
                    name,
                    owner,
                    '2',
                    () => {
                        throw new Error('Broken computed');
                    },
                    invalidate,
                ),
            );
            await flushPromises();
            expect(invalidate).toHaveBeenCalledTimes(1);
            expect(_overridesMap[name]).toHaveLength(1);
        },
    );

    it.activeFeatureFlags(['ADMIN_VITE_8'])(
        'invalidates instead of installing another override if the Vue HMR runtime disappears',
        () => {
            const owner = `${name}:override`;
            const invalidate = jest.fn();
            wrappers.push(mount(registration(name, owner, '1', add(1), invalidate)));
            const target = globalThis as typeof globalThis & { __VUE_HMR_RUNTIME__?: HmrRuntime };
            const saved = target.__VUE_HMR_RUNTIME__;
            delete target.__VUE_HMR_RUNTIME__;
            try {
                wrappers.push(mount(registration(name, owner, '2', add(2), invalidate)));
                expect(invalidate).toHaveBeenCalledTimes(1);
                expect(_overridesMap[name]).toHaveLength(1);
            } finally {
                target.__VUE_HMR_RUNTIME__ = saved;
            }
        },
    );

    it.activeFeatureFlags(['ADMIN_VITE_8'])(
        'uses the newest script when two replacements arrive before the render queue flushes',
        async () => {
            const owner = `${name}:override`;
            const Override = registration(name, owner, '1', add(1));
            runtime.createRecord(owner, Override);
            const Base = base(name);
            const wrapper = mount(defineComponent({ render: () => h('main', [h(Override), h(Base)]) }));
            wrappers.push(wrapper);
            runtime.reload(owner, registration(name, owner, '2', add(2)));
            runtime.reload(owner, registration(name, owner, '3', add(3)));
            await flushPromises();
            expect(wrapper.find('p').text()).toBe('13');
            expect(_overridesMap[name]).toHaveLength(1);
        },
    );

    it.activeFeatureFlags(['ADMIN_VITE_8'])(
        'refreshes the v-once hidden registration mount without remounting its parent',
        async () => {
            const owner = `${name}:override`;
            const Override = registration(name, owner, '1', add(1));
            runtime.createRecord(owner, Override);
            const Base = base(name);
            const Parent = Object.assign(
                defineComponent({
                    components: { Override, Base },
                    template: '<main><input data-parent v-model="draft"><div v-once><Override /></div><Base /></main>',
                    setup: () => ({ draft: ref('initial') }),
                }),
                { __hmrId: `${name}:parent` },
            );
            runtime.createRecord(Parent.__hmrId, Parent);
            const wrapper = mount(Parent);
            wrappers.push(wrapper);
            await flushPromises();
            await wrapper.find('[data-parent]').setValue('unsaved parent');
            runtime.reload(owner, registration(name, owner, '2', add(2)));
            ownerData.get(owner)?.refreshParent?.();
            await flushPromises();
            expect(wrapper.find('p').text()).toBe('12');
            expect((wrapper.find('[data-parent]').element as HTMLInputElement).value).toBe('unsaved parent');
            expect(_overridesMap[name]).toHaveLength(1);
        },
    );

    it.activeFeatureFlags(['ADMIN_VITE_8'])(
        'keeps manual setup registrations working without compiler ownership metadata',
        () => {
            const Override = defineComponent({
                setup() {
                    overrideComponentSetup()(name, add(1) as never);
                    return () => null;
                },
            });
            const Base = defineComponent({
                template: '<p>{{ total }}</p>',
                setup: (props, context) =>
                    createExtendableSetup({ name, props, context }, () => ({
                        public: { total: computed(() => 10) },
                    })),
            });
            wrappers.push(mount(Override));
            const wrapper = mount(Base);
            wrappers.push(wrapper);
            expect(wrapper.text()).toBe('11');
            expect(_overridesMap[name]).toHaveLength(1);
        },
    );

    it('retains append-only registration semantics with Vite 8 inactive', () => {
        wrappers.push(mount(registration(name, `${name}:owner`, '1', add(1))));
        wrappers.push(mount(registration(name, `${name}:owner`, '2', add(2))));
        expect(_overridesMap[name]).toHaveLength(2);
    });

    it.activeFeatureFlags(['ADMIN_VITE_8']).each([false, true])(
        'applies both overrides without HMR bookkeeping in production (Options API: %s)',
        (optionsApi) => {
            const environment = jest.replaceProperty(process.env, 'NODE_ENV', 'production');
            const track = jest.mocked(ScriptHmr.trackSetupHmrComponent);
            const register = jest.mocked(ScriptHmr.registerSetupOverrideForHmr);
            try {
                const owner = `${name}:owner`;
                wrappers.push(mount(registration(name, owner, '1', add(1))));
                wrappers.push(mount(registration(name, owner, '2', add(2))));
                const wrapper = mount(base(name, optionsApi));
                wrappers.push(wrapper);
                expect(wrapper.find('p').text()).toBe('13');
                expect(_overridesMap[name]).toHaveLength(2);
                expect(track).not.toHaveBeenCalled();
                expect(register).not.toHaveBeenCalled();
            } finally {
                environment.restore();
            }
        },
    );

    it.activeFeatureFlags(['ADMIN_VITE_8']).each([false, true])(
        'forwards a production override error to Vue without HMR invalidation (Options API: %s)',
        (optionsApi) => {
            const environment = jest.replaceProperty(process.env, 'NODE_ENV', 'production');
            const invalidate = jest.mocked(ScriptHmr.invalidateFailedSetupHmr);
            const error = new Error('Production override failed');
            const errorHandler = jest.fn<void, [unknown]>();
            let app: App | undefined;
            try {
                wrappers.push(
                    mount(
                        registration(name, `${name}:owner`, '1', () => {
                            throw error;
                        }),
                    ),
                );
                expect(() =>
                    mount(base(name, optionsApi), {
                        global: {
                            config: { errorHandler },
                            plugins: [
                                (mountedApp: App) => {
                                    app = mountedApp;
                                },
                            ],
                        },
                    }),
                ).toThrow(error);
                expect(errorHandler.mock.calls[0]?.[0]).toBe(error);
                expect(invalidate).not.toHaveBeenCalled();
                expect(_overridesMap[name]).toHaveLength(1);
            } finally {
                app?.unmount();
                environment.restore();
            }
        },
    );
});
