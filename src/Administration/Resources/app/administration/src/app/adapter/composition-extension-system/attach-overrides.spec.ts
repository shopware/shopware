/**
 * @sw-package framework
 *
 * Covers attachOverrides(): the extendable-setup entry point that hooks override functionality into
 * an already-executed native <script setup> body.
 */

import { defineComponent, ref, computed } from 'vue';
import { mount } from '@vue/test-utils';
import { overrideComponentSetup, _overridesMap } from 'src/app/adapter/composition-extension-system';
import { attachOverrides } from 'src/app/adapter/composition-extension-system';

describe('src/app/adapter/composition-extension-system attachOverrides', () => {
    beforeEach(() => {
        Object.keys(_overridesMap).forEach((key) => {
            delete _overridesMap[key];
        });
        jest.clearAllMocks();
    });

    /**
     * Simulates the transform's NEW output: fully native body, bindings returned as-is,
     * one generated footer call. The template binds the AUTHOR's refs directly.
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

    it('computed replacement does not reach templates binding the raw author computed (the rename pass restores this)', async () => {
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
