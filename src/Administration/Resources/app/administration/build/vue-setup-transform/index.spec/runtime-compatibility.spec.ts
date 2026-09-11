/** @sw-package framework */
import { mount, type VueWrapper } from '@vue/test-utils';
import { compileScript, parse } from '@vue/compiler-sfc';
import { transpileModule, ModuleKind, ScriptTarget } from 'typescript';
import { h, unref } from 'vue';
import { indexTwigBlocksFromTemplate, resetBlockIndex } from 'src/core/factory/twig-block-index';
import createDataScopeFixture from 'src/app/component/structure/sw-block-override/sw-block-override.spec/test-utils/create-data-scope-fixture';
import { transformOrFail } from './helpers';
import { wrapLegacySlotVNodes } from '../wrap-slot-vnodes';
import ComponentFactory, { type ComponentConfig } from 'src/core/factory/async-component.factory';
import { prepareLegacyComponent } from 'src/app/adapter/options-composition-shim/component-definition';
import { _overridesMap } from 'src/app/adapter/composition-extension-system';

describe('compiled legacy SFC compatibility', () => {
    const wrappers: VueWrapper[] = [];

    beforeEach(() => {
        ComponentFactory.getOverrideRegistry().clear();
        Object.keys(_overridesMap).forEach((name) => {
            delete _overridesMap[name];
        });
        jest.spyOn(console, 'warn').mockImplementation(() => {});
    });
    afterEach(() => {
        wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
        resetBlockIndex();
        jest.restoreAllMocks();
    });

    function compileComponent(
        script: string,
        template = '<div>{{ derived }}|{{ eager }}|{{ mountedValue }}</div>',
        moduleScript = '',
    ) {
        const source = `${moduleScript ? `<script data-sfc-migration-module lang="ts">${moduleScript}</script>` : ''}<script setup lang="ts">${script}</script><template>${template}</template>`;
        const { code } = transformOrFail(source, 'sw-runtime-compatibility.vue');
        const descriptor = parse(code).descriptor;
        const compiled = compileScript(descriptor, { id: 'runtime', inlineTemplate: true });
        const javascript = transpileModule(wrapLegacySlotVNodes(compiled.content, 'runtime.vue')?.code ?? compiled.content, {
            compilerOptions: { module: ModuleKind.CommonJS, target: ScriptTarget.ES2022 },
        }).outputText;
        const exports: { default?: ComponentConfig } = {};
        // Evaluate Vue's actual generated setup/render functions, using the same Vue module as the mounted test.

        // Execute only the Vue compiler output of the trusted inline test fixture.
        // eslint-disable-next-line @typescript-eslint/no-implied-eval
        const execute = new Function('require', 'exports', javascript) as (load: NodeRequire, output: object) => void;
        execute(require, exports);
        return prepareLegacyComponent(
            'sw-runtime-compatibility',
            exports.default!,
            ComponentFactory.getOverrideRegistry().get('sw-runtime-compatibility') ?? [],
        );
    }

    const register = ComponentFactory.register as unknown as (name: string, component: ComponentConfig) => unknown;

    function readExposed(wrapper: VueWrapper, key: string): unknown {
        return unref((wrapper.vm.$.exposed as Record<string, unknown>)[key]);
    }

    it('dispatches internal method calls, captured mounted callbacks and eager computed reads through overrides', () => {
        ComponentFactory.override('sw-runtime-compatibility', {
            methods: {
                value() {
                    return 9;
                },
            },
        });
        const component = compileComponent(`
        import { computed, ref, onMounted } from 'vue';
        const mountedValue = ref(0);
        function value() { return 1; }
        function save() { mountedValue.value = value(); }
        onMounted(save);
        const derived = computed(() => value());
        const eager = ref(derived.value);
        swDefinePublic({ value, save, derived, eager, mountedValue });
    `);
        const wrapper = mount(component);
        wrappers.push(wrapper);
        expect(readExposed(wrapper, 'derived')).toBe(9);
        expect(readExposed(wrapper, 'eager')).toBe(1);
        expect(readExposed(wrapper, 'mountedValue')).toBe(9);
    });

    it('dispatches mapped composable methods through legacy overrides without rerunning the mixin', () => {
        ComponentFactory.override('sw-runtime-compatibility', {
            methods: {
                placeholder(this: { $super: (name: string, ...args: unknown[]) => string }, ...args: unknown[]) {
                    return `${this.$super('placeholder', ...args)}:plugin`;
                },
            },
        });
        const wrapper = mount(
            compileComponent(
                `
            import usePlaceholder from 'src/app/composables/use-placeholder';
            const { placeholder } = usePlaceholder();
            function read() { return placeholder(null, 'name', 'base'); }
            swDefinePublic({ placeholder, read });
        `,
                '<div>{{ read() }}</div>',
            ),
        );
        wrappers.push(wrapper);
        expect(wrapper.text()).toBe('base:plugin');
    });

    it('uses an overridden method when a callback was captured before attachment', () => {
        ComponentFactory.override('sw-runtime-compatibility', {
            methods: {
                save(this: { mountedValue: number }) {
                    this.mountedValue = 7;
                },
            },
        });
        const wrapper = mount(
            compileComponent(
                `
        import { ref, onMounted } from 'vue';
        const mountedValue = ref(0);
        function save() { mountedValue.value = 1; }
        onMounted(save);
        swDefinePublic({ save, mountedValue });
    `,
                '<div>{{ mountedValue }}</div>',
            ),
        );
        wrappers.push(wrapper);
        expect(readExposed(wrapper, 'mountedValue')).toBe(7);
    });

    it('preserves eager calls to hoisted functions and JavaScript temporal dead zones', () => {
        const wrapper = mount(
            compileComponent(
                `
        const eager = value();
        function value() { return 3; }
        swDefinePublic({ eager });
    `,
                '<div>{{ eager }}</div>',
            ),
        );
        wrappers.push(wrapper);
        expect(wrapper.text()).toBe('3');
        expect(() =>
            mount(
                compileComponent(
                    `
        const eager = value;
        const value = (() => 3)();
        swDefinePublic({ eager });
    `,
                    '<div />',
                ),
            ),
        ).toThrow(ReferenceError);
    });

    it('merges definition options before Vue initializes props and executes legacy hooks once', async () => {
        const created = jest.fn();
        ComponentFactory.override('sw-runtime-compatibility', {
            props: { pluginValue: { type: String, default: 'plugin default' } },
            emits: ['save'],
            inheritAttrs: false,
            created(this: { pluginValue: string }) {
                created(this.pluginValue);
            },
            methods: {
                save(this: { $emit: (event: string) => void }) {
                    this.$emit('save');
                },
            },
        });
        ComponentFactory.getComponentRegistry().delete('sw-runtime-compatibility');
        register(
            'sw-runtime-compatibility',
            compileComponent(
                `
        const title = 'base';
        swDefinePublic({ title });
    `,
                '<button @click="save">{{ pluginValue }}</button>',
            ),
        );
        const definition = await ComponentFactory.build('sw-runtime-compatibility');
        if (typeof definition === 'boolean') throw new Error('Failed to build test component');
        const onSave = jest.fn();
        const wrapper = mount(definition, { attrs: { title: 'must not fall through', onSave } });
        wrappers.push(wrapper);
        expect(wrapper.text()).toBe('plugin default');
        expect(wrapper.attributes('title')).toBeUndefined();
        expect(created.mock.calls).toEqual([['plugin default']]);
        await wrapper.trigger('click');
        expect(onSave).toHaveBeenCalledTimes(1);
    });

    it('runs a custom legacy render with host state even when Vue emits an inline setup render', async () => {
        const created = jest.fn();
        ComponentFactory.override('sw-runtime-compatibility', {
            created,
            data: () => ({ pluginValue: 'plugin' }),
            render(this: { pluginValue: string }) {
                return h('b', this.pluginValue);
            },
        });
        ComponentFactory.getComponentRegistry().delete('sw-runtime-compatibility');
        register('sw-runtime-compatibility', compileComponent('swDefinePublic({});', '<i>base</i>'));
        const definition = await ComponentFactory.build('sw-runtime-compatibility');
        if (typeof definition === 'boolean') throw new Error('Failed to build test component');
        const wrapper = mount(definition);
        wrappers.push(wrapper);
        expect(wrapper.get('b').text()).toBe('plugin');
        expect(created).toHaveBeenCalledTimes(1);
    });

    it('keeps Component.extend state and base overrides isolated from the base component', async () => {
        ComponentFactory.getComponentRegistry().delete('sw-runtime-compatibility');
        ComponentFactory.getComponentRegistry().delete('sw-runtime-derived');
        register(
            'sw-runtime-compatibility',
            compileComponent(
                `
        function value() { return 'base'; }
        swDefinePublic({ value });
    `,
                '<div>{{ value() }}</div>',
            ),
        );
        ComponentFactory.override('sw-runtime-compatibility', {
            methods: {
                value(this: { $super: (name: string) => string }) {
                    return this.$super('value') + ':plugin';
                },
            },
        });
        ComponentFactory.extend('sw-runtime-derived', 'sw-runtime-compatibility', {
            methods: {
                value(this: { $super: (name: string) => string }) {
                    return this.$super('value') + ':derived';
                },
            },
        });
        const base = await ComponentFactory.build('sw-runtime-compatibility');
        const derived = await ComponentFactory.build('sw-runtime-derived');
        if (typeof base === 'boolean' || typeof derived === 'boolean') throw new Error('Failed to build test components');
        const baseWrapper = mount(base);
        const derivedWrapper = mount(derived);
        wrappers.push(baseWrapper, derivedWrapper);
        expect(baseWrapper.text()).toBe('base:plugin');
        expect(derivedWrapper.text()).toBe('base:plugin:derived');
    });

    it('forwards destructured loop and slot variables into a Twig override without losing the host scope', () => {
        indexTwigBlocksFromTemplate(
            'sw-runtime-compatibility',
            `{% block row %}<i>{{ label }}:{{ title }}</i>{% endblock %}`,
        );
        const component = compileComponent(
            `
        const title = 'host';
        const rows = [{ label: 'first' }, { label: 'second' }];
        swDefinePublic({ title, rows });
    `,
            '<main><div v-for="{ label } in rows" :key="label"><sw-block name="row"><b>{{ label }}</b></sw-block></div></main>',
        );
        const wrapper = mount(component, { global: { plugins: [createDataScopeFixture()] } });
        wrappers.push(wrapper);
        expect(wrapper.findAll('i').map((node) => node.text())).toEqual([
            'first:host',
            'second:host',
        ]);
    });

    it('bridges renamed legacy members while keeping the native public surface unchanged', () => {
        ComponentFactory.override('sw-runtime-compatibility', {
            methods: {
                oldValue(this: { $super: (name: string) => string }) {
                    return this.$super('oldValue') + ':plugin';
                },
            },
        });
        const component = compileComponent(
            `
        defineOptions({ legacyOptionsBindings: { oldValue: 'internalValue' } });
        function internalValue() { return 'base'; }
        function publicValue() { return internalValue(); }
        swDefinePublic({ publicValue });
    `,
            '<div>{{ publicValue() }}</div>',
        );
        const wrapper = mount(component);
        wrappers.push(wrapper);
        expect(wrapper.text()).toBe('base:plugin');
        expect(wrapper.vm.$.exposed).not.toHaveProperty('internalValue');
        expect(wrapper.vm.$.exposed).not.toHaveProperty('oldValue');
    });

    it('keeps reassigned setup variables connected to the rendered state', async () => {
        const component = compileComponent(
            `
        let count = 1;
        function increment() { count++; }
        function replace() { ({ count } = { count: 5 }); }
        swDefinePublic({ count, increment, replace });
    `,
            '<div><button @click="increment">{{ count }}</button><b @click="replace">replace</b></div>',
        );
        const wrapper = mount(component);
        wrappers.push(wrapper);
        await wrapper.get('button').trigger('click');
        expect(wrapper.get('button').text()).toBe('2');
        await wrapper.get('b').trigger('click');
        expect(wrapper.get('button').text()).toBe('5');
    });

    it('merges named slot descriptors before mounting the original receiver and preserves scoped parent content', () => {
        indexTwigBlocksFromTemplate(
            'sw-runtime-compatibility',
            `
        {% block receiver_slots %}
            {% parent %}
            <template #content="{ label }"><strong>{{ label }} plugin</strong>{% parent %}{% parent %}</template>
        {% endblock %}
    `,
        );
        const component = compileComponent(
            `
        import { defineComponent, markRaw, h } from 'vue';
        const Receiver = markRaw(defineComponent({
            name: 'slot-receiver',
            setup(_, { slots }) {
                return () => h('section', [slots.content?.({ label: 'scoped' }), slots.footer?.()]);
            },
        }));
        swDefinePublic({});
    `,
            `<Receiver ref="receiver" title="kept"><sw-block name="receiver_slots">
        <template #content="{ label }"><b>{{ label }} base</b></template>
        <template #footer><i>footer</i></template>
    </sw-block></Receiver>`,
        );
        const wrapper = mount(component, { global: { plugins: [createDataScopeFixture()] } });
        wrappers.push(wrapper);
        expect(wrapper.get('strong').text()).toBe('scoped plugin');
        expect(wrapper.findAll('b').map((node) => node.text())).toEqual([
            'scoped base',
            'scoped base',
        ]);
        expect(wrapper.get('i').text()).toBe('footer');
        expect(wrapper.get('section').attributes('title')).toBe('kept');
        expect((wrapper.vm.$refs.receiver as { $options: { name: string } }).$options.name).toBe('slot-receiver');
    });

    it('supports dynamic slot names and a Twig replacement that removes the preceding slot descriptors', () => {
        indexTwigBlocksFromTemplate(
            'sw-runtime-compatibility',
            `
        {% block receiver_slots %}<template #[selected]="{ label }"><b>{{ label }} replacement</b></template>{% endblock %}
    `,
        );
        const component = compileComponent(
            `
        import { defineComponent, markRaw, h } from 'vue';
        const selected = 'first';
        const slotNames = ['first', 'second'];
        const Receiver = markRaw(defineComponent({
            setup(_, { slots }) { return () => h('section', Object.keys(slots).map((name) => slots[name]({ label: name }))); },
        }));
        swDefinePublic({ selected });
    `,
            `<Receiver><sw-block name="receiver_slots"><template v-for="name in slotNames" #[name]="{ label }"><i>{{ label }} base</i></template></sw-block></Receiver>`,
        );
        const wrapper = mount(component, { global: { plugins: [createDataScopeFixture()] } });
        wrappers.push(wrapper);
        expect(wrapper.text()).toBe('first replacement');
        expect(wrapper.findAll('i')).toHaveLength(0);
    });

    it('makes lexical SFC components and directives available in Twig and honors legacy replacements in both templates', async () => {
        const mounted = jest.fn((element: HTMLElement) => {
            element.dataset.legacy = 'yes';
        });
        ComponentFactory.override('sw-runtime-compatibility', {
            components: { LocalBadge: { render: () => h('b', 'replacement') } },
            directives: { highlight: { mounted } },
            template: '{% block content %}<local-badge v-highlight />{% endblock %}',
        });
        const component = compileComponent(
            `
        import { defineComponent, markRaw, h } from 'vue';
        const LocalBadge = markRaw(defineComponent({ render: () => h('i', 'base') }));
        const vHighlight = { mounted(element) { element.dataset.base = 'yes'; } };
        swDefinePublic({});
    `,
            '<main><LocalBadge v-highlight /><sw-block name="content"><LocalBadge /></sw-block></main>',
        );
        ComponentFactory.getComponentRegistry().delete('sw-runtime-compatibility');
        register('sw-runtime-compatibility', component);
        const definition = await ComponentFactory.build('sw-runtime-compatibility');
        if (typeof definition === 'boolean') throw new Error('Failed to build fixture');
        const wrapper = mount(definition, { global: { plugins: [createDataScopeFixture()] } });
        wrappers.push(wrapper);
        expect(wrapper.findAll('b').map((node) => node.text())).toEqual([
            'replacement',
            'replacement',
        ]);
        expect(wrapper.findAll('[data-legacy="yes"]')).toHaveLength(2);
        expect(mounted).toHaveBeenCalledTimes(2);
    });

    it('resolves setup-local assets in Twig without an Options registration', () => {
        indexTwigBlocksFromTemplate(
            'sw-runtime-compatibility',
            '{% block content %}<local-badge v-highlight />{% endblock %}',
        );
        const component = compileComponent(
            `
        import { defineComponent, markRaw, h } from 'vue';
        const LocalBadge = markRaw(defineComponent({ render: () => h('b', 'lexical') }));
        const vHighlight = { mounted(element) { element.dataset.highlight = 'yes'; } };
        swDefinePublic({});
    `,
            '<main><LocalBadge v-highlight /><sw-block name="content"><LocalBadge /></sw-block></main>',
        );
        const wrapper = mount(component, { global: { plugins: [createDataScopeFixture()] } });
        wrappers.push(wrapper);
        expect(wrapper.findAll('b')).toHaveLength(2);
        expect(wrapper.findAll('[data-highlight="yes"]')).toHaveLength(2);
    });

    it('keeps conditional Twig content isolated between loop rows', async () => {
        indexTwigBlocksFromTemplate(
            'sw-runtime-compatibility',
            '{% block row %}<b v-if="visible">yes</b><i v-else>no</i>{% endblock %}',
        );
        const component = compileComponent(
            `
        const rows = [{ id: 1, visible: true }, { id: 2, visible: false }];
        swDefinePublic({ rows });
    `,
            '<main><div v-for="{ id, visible } in rows" :key="id"><sw-block name="row"><span>base</span></sw-block></div></main>',
        );
        const wrapper = mount(component, { global: { plugins: [createDataScopeFixture()] } });
        wrappers.push(wrapper);
        await flushPromises();
        expect(wrapper.findAll('main > div').map((row) => row.text())).toEqual([
            'yes',
            'no',
        ]);
    });
    it('retains module-script assets produced by the migration codemod', () => {
        indexTwigBlocksFromTemplate(
            'sw-runtime-compatibility',
            '{% block content %}<local-badge v-highlight />{% endblock %}',
        );
        const component = compileComponent(
            'swDefinePublic({});',
            '<main><LocalBadge v-highlight /><sw-block name="content"><LocalBadge /></sw-block></main>',
            `import { defineComponent, h } from 'vue';
             const LocalBadge = defineComponent({ render: () => h('b', 'module asset') });
             const vHighlight = { mounted(element) { element.dataset.module = 'yes'; } };`,
        );
        const wrapper = mount(component, { global: { plugins: [createDataScopeFixture()] } });
        wrappers.push(wrapper);
        expect(wrapper.findAll('[data-module="yes"]')).toHaveLength(2);
    });
    it('does not replace built-in v-model or dynamic component syntax with similarly named setup assets', async () => {
        const component = compileComponent(
            `
            import { ref } from 'vue';
            const vModel = false;
            const component = false;
            const value = ref('initial');
            swDefinePublic({ value });
        `,
            '<component is="section"><input v-model="value" /></component>',
        );
        const wrapper = mount(component);
        wrappers.push(wrapper);
        await wrapper.get('input').setValue('edited');
        expect(readExposed(wrapper, 'value')).toBe('edited');
        expect(wrapper.find('section').exists()).toBe(true);
    });
});
