/**
 * @sw-package framework
 *
 * Covers attachOverrides(): the extendable-setup entry point that hooks override functionality into
 * an already-executed native <script setup> body.
 */

import { defineComponent, ref, computed, onBeforeMount } from 'vue';
import { mount } from '@vue/test-utils';
import { attachOverrides, overrideComponentSetup, _overridesMap } from 'src/app/adapter/composition-extension-system';

describe('src/app/adapter/composition-extension-system attachOverrides', () => {
    beforeEach(() => {
        Object.keys(_overridesMap).forEach((key) => {
            delete _overridesMap[key];
        });
        jest.clearAllMocks();
    });

    /**
     * Simulates an author body WITHOUT the rename pass: the template binds the author's own refs
     * directly, so the footer's returned keys are never read.
     *
     * This is deliberately NOT what the transform emits - see `createLoweredBase()` for that. It is
     * kept because it isolates what `attachOverrides()` can and cannot do on its own, which is the
     * reason the rename pass exists at all.
     */
    function createBase(name: string) {
        return defineComponent({
            template: '<div>{{ count }}|{{ doubled }}</div>',
            setup(props, { expose }) {
                // -- author code, native --
                const count = ref(1);
                const doubled = computed(() => count.value * 2);

                // -- generated footer --
                attachOverrides({
                    name,
                    public: { count, doubled },
                    private: {},
                });

                expose({});

                return { count, doubled };
            },
        });
    }

    /**
     * Simulates the transform's ACTUAL base output: every top-level author binding renamed to its
     * `__swSetupAuthor_` alias, and the original names re-declared by destructuring the generated
     * `attachOverrides(...)` footer. The template binds the footer's names, not the author's refs.
     *
     * This is the shape `build/vue-setup-transform` emits for `sw-thing.vue`, so it is the shape the
     * override contract has to hold for.
     */
    function createLoweredBase(name: string) {
        return defineComponent({
            template: '<div>{{ count }}|{{ doubled }}</div>',
            setup(props, { expose }) {
                // -- author code, native, renamed by the transform --
                const __swSetupAuthor_count = ref(1);
                const __swSetupAuthor_doubled = computed(() => __swSetupAuthor_count.value * 2);

                // -- generated footer --
                const { count, doubled } = attachOverrides({
                    name,
                    public: {
                        count: __swSetupAuthor_count,
                        doubled: __swSetupAuthor_doubled,
                    },
                    private: {},
                }) as unknown as { count: unknown; doubled: unknown };

                expose({});

                return { count, doubled };
            },
        });
    }

    it('lowered shape: a public computed is not evaluated during setup', () => {
        const evaluate = jest.fn(() => 2);

        const base = defineComponent({
            template: '<div />',
            setup() {
                const __swSetupAuthor_doubled = computed(evaluate);

                attachOverrides({
                    name: 'loweredLazyComputed',
                    public: { doubled: __swSetupAuthor_doubled },
                    private: {},
                });

                return {};
            },
        });

        const wrapper = mount(base);

        // The template never reads `doubled`, so nothing should have run it.
        expect(evaluate).not.toHaveBeenCalled();

        wrapper.unmount();
    });

    it('lowered shape: a public computed may depend on state a lifecycle hook initializes', () => {
        const base = defineComponent({
            template: '<div>{{ derived }}</div>',
            setup() {
                const __swSetupAuthor_lateState = ref<Record<string, number> | null>(null);
                const __swSetupAuthor_derived = computed(() => Object.keys(__swSetupAuthor_lateState.value!).length);

                onBeforeMount(() => {
                    __swSetupAuthor_lateState.value = { a: 1 };
                });

                const { lateState, derived } = attachOverrides({
                    name: 'loweredLateState',
                    public: {
                        lateState: __swSetupAuthor_lateState,
                        derived: __swSetupAuthor_derived,
                    },
                    private: {},
                }) as unknown as { lateState: unknown; derived: unknown };

                return { lateState, derived };
            },
        });

        // Evaluating the computed while building the data scope threw here, because `lateState` was
        // still `null` - the hook that fills it only runs after setup.
        const wrapper = mount(base);

        expect(wrapper.text()).toBe('1');

        wrapper.unmount();
    });

    it('lowered shape: a computed replacement DOES reach the template', async () => {
        const wrapper = mount(createLoweredBase('loweredComputed'));
        expect(wrapper.text()).toBe('1|2');

        overrideComponentSetup()('loweredComputed', () => {
            return { doubled: computed(() => 999) } as never;
        });
        await flushPromises();

        // This is the whole point of the rename pass. The footer binding is a `toRef` into the wrapper's
        // reactive state, so rebinding the `doubled` key there is visible to the template - unlike the
        // un-renamed shape above, which stays at 1|2.
        expect(wrapper.text()).toBe('1|999');
    });

    it('lowered shape: a plain ref replacement reaches the template too', async () => {
        const wrapper = mount(createLoweredBase('loweredRef'));
        expect(wrapper.text()).toBe('1|2');

        overrideComponentSetup()('loweredRef', () => ({ count: ref(5) }) as never);
        await flushPromises();

        // syncRef writes into the author's original ref, so the author's derived computed recomputes.
        expect(wrapper.text()).toBe('5|10');
    });

    it('plain ref replacement reaches the template through the original ref (syncRef)', async () => {
        const wrapper = mount(createBase('extendableRef'));
        expect(wrapper.text()).toBe('1|2');

        overrideComponentSetup()('extendableRef', () => ({ count: ref(5) }) as never);
        await flushPromises();

        // The override's ref is synced into the author's original ref - template updates,
        // and the derived author computed recomputes from it.
        expect(wrapper.text()).toBe('5|10');
    });

    it('previous state reads the live base value', async () => {
        let observed: unknown;

        mount(createBase('extendablePrev'));

        overrideComponentSetup()('extendablePrev', (previousState) => {
            observed = (previousState as Record<string, { value: unknown }>).count.value;
            return { count: ref(7) } as never;
        });
        await flushPromises();

        expect(observed).toBe(1);
    });

    it('override-local state reaches the data scope', async () => {
        let scope: Record<string, unknown> | undefined;

        const base = defineComponent({
            template: '<div>{{ count }}</div>',
            setup() {
                const count = ref(1);
                scope = attachOverrides({
                    name: 'extendableLocal',
                    public: { count },
                }) as unknown as Record<string, unknown>;
                return { count };
            },
        });

        mount(base);
        overrideComponentSetup()('extendableLocal', () => {
            return { __swOverride: { 'plugin/a': { msg: 'local' } } } as never;
        });
        await flushPromises();

        expect((scope!.__swOverride as { value: unknown }).value).toEqual({ 'plugin/a': { msg: 'local' } });
    });

    it('un-renamed shape: a computed replacement does NOT reach a template binding the raw author computed', async () => {
        const wrapper = mount(createBase('extendableComputed'));
        expect(wrapper.text()).toBe('1|2');

        overrideComponentSetup()('extendableComputed', () => {
            return { doubled: computed(() => 999) } as never;
        });
        await flushPromises();

        // The template binds the author's original `doubled` computed; the replacement only
        // rebinds the wrapper key. With createExtendableSetup() this would show 1|999.
        expect(wrapper.text()).toBe('1|2');
    });

    it('later overrides see a computed replacement through previousState', async () => {
        let observed: unknown;

        mount(createBase('extendableChain'));

        overrideComponentSetup()('extendableChain', () => {
            return { doubled: computed(() => 999) } as never;
        });
        await flushPromises();

        overrideComponentSetup()('extendableChain', (previousState) => {
            observed = (previousState as Record<string, { value: unknown }>).doubled;
            return {} as never;
        });
        await flushPromises();

        // The wrapper state carries the replacement, so the chain semantics survive -
        // only the template binding is stale.
        expect((observed as { value: unknown }).value).toBe(999);
    });
});
