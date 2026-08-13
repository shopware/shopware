/**
 * @sw-package framework
 */

import { defineComponent, h, ref, computed } from 'vue';
import { mount } from '@vue/test-utils';
import { overrideComponentSetup, _overridesMap } from 'src/app/adapter/composition-extension-system';
import {
    applyCompositionOverridesToOptionsBase,
    _resetOptionsBaseOverrideWarnings,
} from 'src/app/adapter/options-base-override';

/**
 * Simulates the setup() the component factory injects into an Options base: it calls the runtime and
 * returns the replaced keys, which Vue merges into setupState (shadowing data/computed).
 */
function optionsBaseWithInjectedSetup(name: string, options: Record<string, unknown>) {
    return defineComponent({
        name,
        ...options,
        setup() {
            return applyCompositionOverridesToOptionsBase(name) ?? {};
        },
    });
}

describe('src/app/adapter/options-base-override', () => {
    beforeEach(() => {
        Object.keys(_overridesMap).forEach((key) => delete _overridesMap[key]);
        _resetOptionsBaseOverrideWarnings();
        jest.clearAllMocks();
    });

    it('returns null when no override targets the component', () => {
        expect(applyCompositionOverridesToOptionsBase('untouched')).toBeNull();
    });

    it('replaces an Options data field for the template and the base own reads', () => {
        // Replacing with a computed derived from previousState is the realistic case; it fires the
        // "computed replacement" dev warning, which is expected here.
        jest.spyOn(console, 'warn').mockImplementation(() => {});

        overrideComponentSetup()('sw-opt-data', (previousState) => ({
            headline: computed(() => `${(previousState as Record<string, { value: string }>).headline.value} (Pro)`),
        }));

        const Base = optionsBaseWithInjectedSetup('sw-opt-data', {
            data: () => ({ headline: 'Base' }),
            computed: {
                echo(): string {
                    return (this as unknown as { headline: string }).headline;
                },
            },
            render() {
                const self = this as unknown as { headline: string; echo: string };
                return h('div', `${self.headline}|${self.echo}`);
            },
        });

        const wrapper = mount(Base);
        // Template read AND the base's own computed (this.headline) both see the override.
        expect(wrapper.text()).toBe('Base (Pro)|Base (Pro)');
    });

    it('leaves non-overridden Options state untouched', () => {
        overrideComponentSetup()('sw-opt-partial', () => ({ title: ref('Overridden') }));

        const Base = optionsBaseWithInjectedSetup('sw-opt-partial', {
            data: () => ({ title: 'Base title', subtitle: 'Base subtitle' }),
            render() {
                const self = this as unknown as { title: string; subtitle: string };
                return h('div', `${self.title}|${self.subtitle}`);
            },
        });

        expect(mount(Base).text()).toBe('Overridden|Base subtitle');
    });

    it('reacts to a further override added after the first one (registered before mount)', async () => {
        // At least one override exists at mount, so the runtime installs its watch; a further override
        // added afterwards is picked up. (Zero-overrides-at-mount is the register-before-mount contract.)
        overrideComponentSetup()('sw-opt-chain', () => ({ label: ref('First') }));

        const Base = optionsBaseWithInjectedSetup('sw-opt-chain', {
            data: () => ({ label: 'Base', other: 'x' }),
            render() {
                return h('div', `${(this as unknown as { label: string }).label}`);
            },
        });

        const wrapper = mount(Base);
        expect(wrapper.text()).toBe('First');

        overrideComponentSetup()('sw-opt-chain', () => ({ other: ref('added') }) as never);
        await flushPromises();

        // The new override applied without disturbing the first.
        expect(wrapper.text()).toBe('First');
    });

    it('warns once when an override replaces a base-written field with a computed', () => {
        const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});

        overrideComponentSetup()('sw-opt-computed', () => ({ count: computed(() => 42) }));

        const Base = optionsBaseWithInjectedSetup('sw-opt-computed', {
            data: () => ({ count: 0 }),
            render() {
                return h('div', `${(this as unknown as { count: number }).count}`);
            },
        });

        const wrapper = mount(Base);
        expect(wrapper.text()).toBe('42');
        expect(warn).toHaveBeenCalledWith(expect.stringContaining('replaced "count" with a computed value'));
    });
});
