/** @sw-package framework */
import { mount, type VueWrapper } from '@vue/test-utils';
import { compileScript, parse } from '@vue/compiler-sfc';
import { transpileModule, ModuleKind, ScriptTarget } from 'typescript';
import { h, nextTick } from 'vue';
import ComponentFactory, {
    type ComponentConfig,
    type IndexedAwaitedComponentConfig,
} from 'src/core/factory/async-component.factory';
import { prepareLegacyComponent } from 'src/app/adapter/options-composition-shim/component-definition';
import { transformOrFail } from './helpers';

function compile(overrides: ComponentConfig[], base: ComponentConfig = {}): ComponentConfig {
    const source = `<script setup lang="ts">
        import { ref } from 'vue';
        const count = ref(1);
        function hidden() { return 'base'; }
        swDefinePublic({ count, hidden });
    </script><template><div>{{ count }}</div></template>`;
    const { code } = transformOrFail(source, 'sw-exposure-compatibility.vue');
    const compiled = compileScript(parse(code).descriptor, { id: 'exposure', inlineTemplate: true });
    const javascript = transpileModule(compiled.content, {
        compilerOptions: { module: ModuleKind.CommonJS, target: ScriptTarget.ES2022 },
    }).outputText;
    const exports: { default?: ComponentConfig } = {};
    // Execute only the Vue compiler output of this trusted inline fixture.
    // eslint-disable-next-line @typescript-eslint/no-implied-eval
    const execute = new Function('require', 'exports', javascript) as (load: NodeRequire, output: object) => void;
    execute(require, exports);
    return prepareLegacyComponent(
        'sw-exposure-compatibility',
        { ...exports.default!, ...base },
        overrides.map((resolvedConfig) => ({ resolvedConfig }) as IndexedAwaitedComponentConfig),
    );
}

describe('legacy expose lists on compiled SFC components', () => {
    const wrappers: VueWrapper[] = [];
    afterEach(() => {
        wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
        jest.restoreAllMocks();
    });

    function parent(component: ComponentConfig) {
        const wrapper = mount({ render: () => h(component, { ref: 'child' }) });
        wrappers.push(wrapper);
        return { wrapper, child: wrapper.vm.$refs.child as Record<string, unknown> };
    }

    it('preserves an empty expose list while retaining Vue public instance properties', () => {
        const { child } = parent(compile([{ expose: [] }]));
        expect(child.count).toBeUndefined();
        expect(child.hidden).toBeUndefined();
        expect(child.$data).toBeDefined();
    });

    it('exposes only selected base members and forwards parent writes', async () => {
        const { child, wrapper } = parent(compile([{ expose: ['count'] }]));
        expect(child.count).toBe(1);
        expect(child.hidden).toBeUndefined();
        child.count = 7;
        await nextTick();
        expect(wrapper.text()).toBe('7');
    });

    it('exposes members added by an override without exposing unselected SFC members', () => {
        const { child } = parent(
            compile([
                {
                    expose: ['pluginValue'],
                    data: () => ({ pluginValue: 'plugin' }),
                },
            ]),
        );
        expect(child.pluginValue).toBe('plugin');
        expect(child.count).toBeUndefined();
        expect(child.hidden).toBeUndefined();
    });

    it('keeps an expose list on a nested mixin inactive', () => {
        const warning = jest.spyOn(console, 'warn').mockImplementation(() => {});
        const { child } = parent(compile([{ mixins: [{ expose: [] }] }]));
        expect(child.count).toBe(1);
        expect(child.hidden).toEqual(expect.any(Function));
        expect(warning.mock.calls[0][0]).toEqual(expect.stringContaining('"expose" option is ignored'));
    });

    it('retains the last declared list when a later override omits expose, like the legacy factory', async () => {
        jest.spyOn(console, 'warn').mockImplementation(() => {});
        const overrides: ComponentConfig[] = [
            { expose: [] },
            { methods: { pluginValue: () => 'plugin' } },
        ];
        ComponentFactory.register('sw-exposure-native-control', {
            data: () => ({ count: 1 }),
            methods: { hidden: () => 'base' },
            render: () => h('div'),
        });
        overrides.forEach((override) => ComponentFactory.override('sw-exposure-native-control', override));
        const native = parent((await ComponentFactory.build('sw-exposure-native-control', true)) as ComponentConfig).child;
        const migrated = parent(compile(overrides)).child;
        expect(migrated.count).toBe(native.count);
        expect(typeof migrated.hidden).toBe(typeof native.hidden);
        expect(typeof migrated.pluginValue).toBe(typeof native.pluginValue);
    });

    it('replaces an earlier expose list with the last override list', () => {
        jest.spyOn(console, 'warn').mockImplementation(() => {});
        const { child } = parent(
            compile([
                { expose: ['hidden'] },
                { expose: ['count'] },
            ]),
        );
        expect(child.count).toBe(1);
        expect(child.hidden).toBeUndefined();
    });

    it('preserves the original root expose list when there are no overrides', () => {
        const { child } = parent(compile([], { expose: ['count'] }));
        expect(child.count).toBe(1);
        expect(child.hidden).toBeUndefined();
    });
});
