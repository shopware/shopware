/**
 * @sw-package framework
 */

/* eslint-disable @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-call, @typescript-eslint/no-explicit-any */

import { computed, defineComponent, h, ref } from 'vue';
import { mount } from '@vue/test-utils';
import type { ComponentConfig } from 'src/core/factory/async-component.factory';
import { _overridesMap, overrideComponentSetup } from 'src/app/adapter/composition-extension-system';

const componentName = 'sw-options-shim-real-path';

/** The shape `@vitejs/plugin-vue` compiles a native setup SFC into: a setup function plus a render function. */
const NativeBase = defineComponent({
    setup() {
        const __swSetupAuthor_count = ref(0);
        const __swSetupAuthor_label = computed(() => 'Count');
        const __swSetupAuthor_increment = () => {
            __swSetupAuthor_count.value += 1;
        };

        return Shopware.Component.__setupRuntime.v1.attach({
            name: componentName,
            public: {
                count: __swSetupAuthor_count,
                label: __swSetupAuthor_label,
                increment: __swSetupAuthor_increment,
            },
        });
    },
    render(ctx: any) {
        return h('button', { type: 'button', onClick: ctx.increment }, `${ctx.label as string}: ${ctx.count as number}`);
    },
});

function override(config: Record<string, any>, index: number | null = null): void {
    Shopware.Component.override(componentName, config as ComponentConfig, index);
}

async function buildFixture(): Promise<ComponentConfig> {
    const built = await Shopware.Component.build(componentName);

    if (typeof built === 'boolean') {
        throw new Error(`${componentName} could not be built`);
    }

    return built;
}

function mountFixtures(component: ComponentConfig, count: number) {
    const parent = defineComponent({
        render: () => Array.from({ length: count }, () => h(component as any)),
    });

    return mount(parent, {
        global: {
            provide: {
                pluginService: { name: 'plugin service' },
            },
        },
    });
}

describe('src/app/adapter/options-composition-shim (real Shopware.Component.override() path)', () => {
    let warn: jest.SpyInstance;

    beforeEach(() => {
        Shopware.Component.getComponentRegistry().clear();
        Shopware.Component.getOverrideRegistry().clear();
        _overridesMap.clear();
        warn = jest.spyOn(console, 'warn').mockImplementation(() => {});

        (Shopware.Component.register as (componentName: string, config: unknown) => void)(componentName, NativeBase);
    });

    afterEach(() => {
        warn.mockRestore();
    });

    it('applies an Options API override once per instance, with a real component instance', async () => {
        const created = jest.fn();
        const translated: unknown[] = [];
        const injected: unknown[] = [];

        override({
            inject: ['pluginService'],
            created() {
                created();
                translated.push(this.$t('sw-plugin.label'));
                injected.push(this.pluginService);
            },
            methods: {
                increment() {
                    this.count += 10;
                },
            },
        });

        const wrapper = mountFixtures(await buildFixture(), 3);
        const buttons = wrapper.findAll('button');

        expect(created).toHaveBeenCalledTimes(3);
        expect(translated).toEqual([
            'sw-plugin.label',
            'sw-plugin.label',
            'sw-plugin.label',
        ]);
        expect(injected).toEqual(Array(3).fill({ name: 'plugin service' }));
        expect(warn).not.toHaveBeenCalledWith(expect.stringContaining('injection "pluginService" not found'));

        await buttons[0].trigger('click');

        expect(buttons.map((button) => button.text())).toEqual([
            'Count: 10',
            'Count: 0',
            'Count: 0',
        ]);
        expect(_overridesMap.has(componentName)).toBe(false);
    });

    it('converts the Options API overrides of a component only once', async () => {
        override({
            methods: {
                increment() {
                    this.count += 2;
                },
            },
        });

        const component = await buildFixture();
        mountFixtures(component, 2);
        mountFixtures(component, 2);

        const deprecationWarnings = warn.mock.calls.filter(([message]) =>
            String(message).startsWith('[Deprecation Warning]'),
        );
        expect(deprecationWarnings).toHaveLength(1);
    });

    it('keeps the render function of the native component when building it with Options API overrides', async () => {
        override({
            created() {},
        });

        const component = await buildFixture();

        expect(typeof component.render).toBe('function');
        expect(component.extends).toBeUndefined();
    });

    it('runs lifecycle hooks registered after setup, including unmount hooks', async () => {
        const hooks: string[] = [];

        override({
            mounted() {
                hooks.push(`mounted:${String(this.count)}`);
            },
            unmounted() {
                hooks.push('unmounted');
            },
        });

        const wrapper = mountFixtures(await buildFixture(), 1);
        wrapper.unmount();

        expect(hooks).toEqual([
            'mounted:0',
            'unmounted',
        ]);
    });

    it('applies Options API overrides in factory index order and chains $super through them', async () => {
        override(
            {
                computed: {
                    label() {
                        return `${this.$super('label') as string} (B)`;
                    },
                },
            },
            2,
        );
        override(
            {
                computed: {
                    label() {
                        return `${this.$super('label') as string} (A)`;
                    },
                },
            },
            1,
        );

        const wrapper = mountFixtures(await buildFixture(), 1);

        expect(wrapper.get('button').text()).toBe('Count (A) (B): 0');
    });

    it('applies composition overrides before Options API overrides', async () => {
        overrideComponentSetup()(componentName, (previousState) => ({
            increment: () => {
                previousState.count.value += 100;
            },
        }));
        override({
            methods: {
                increment() {
                    this.$super('increment');
                    this.count += 1;
                },
            },
        });

        const wrapper = mountFixtures(await buildFixture(), 1);
        await wrapper.get('button').trigger('click');

        expect(wrapper.get('button').text()).toBe('Count: 101');
    });

    it('skips overrides that were not resolved yet and warns about them', () => {
        override({
            methods: {
                increment() {
                    this.count += 5;
                },
            },
        });

        const wrapper = mount(NativeBase);

        expect(warn).toHaveBeenCalledWith(
            expect.stringContaining(`1 override(s) of "${componentName}" are not resolved yet and are skipped`),
        );
        expect(wrapper.get('button').text()).toBe('Count: 0');
    });
});
