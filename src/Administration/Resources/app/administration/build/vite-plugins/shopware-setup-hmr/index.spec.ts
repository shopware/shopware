/**
 * @sw-package framework
 */
import { flushPromises, mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import type { Component } from 'vue';
import { computed, defineComponent, h, nextTick, ref } from 'vue';
import { createExtendableSetup } from 'src/app/adapter/composition-extension-system';
import ComponentFactory from 'src/core/factory/async-component.factory';
import type { ComponentConfig } from 'src/core/factory/async-component.factory';
import type { ShopwareSetupHmrMeta } from '../shopware-setup';
import ShopwareSetupHmrPlugin from './index';

type HotData = Record<string, unknown>;
type ModuleHot = {
    data: HotData;
    on: () => void;
    accept: (callback: unknown) => void;
    invalidate: jest.Mock;
};
type AcceptHandler = (module?: { default: unknown }) => void;
type EvaluatedModule = { component: Component; hot: ModuleHot; acceptHandlers: AcceptHandler[] };

type Version = { version: string; label: string; count: number; structure?: string };

const file = '/plugin/src/sw-hmr.vue';
const runtime = (globalThis as typeof globalThis & { __VUE_HMR_RUNTIME__: { CHANGED_FILE?: string } }).__VUE_HMR_RUNTIME__;

let sequence = 0;

/**
 * The tail @vitejs/plugin-vue 6 appends to an SFC module in development, around a small component
 * whose script is `count` and whose template is `label`.
 */
function pluginVueOutput({
    hmrId,
    name,
    label,
    count,
}: Omit<Version, 'version' | 'structure'> & { hmrId: string; name: string }) {
    return `
const _sfc_main = {
    setup(__props, context) {
        return createExtendableSetup({ name: ${JSON.stringify(name)}, props: __props, context }, () => ({
            public: { count: computed(() => ${count}), draft: ref('initial') },
        }));
    },
};
function _sfc_render(_ctx) {
    return h('section', [
        h('input', { value: _ctx.draft, onInput: (event) => { _ctx.draft = event.target.value; } }),
        h('p', ${JSON.stringify(label)} + ' ' + _ctx.count),
    ]);
}
const _export_sfc = (sfc, props) => { for (const [key, value] of props) sfc[key] = value; return sfc; };
_sfc_main.__hmrId = ${JSON.stringify(hmrId)}
typeof __VUE_HMR_RUNTIME__ !== 'undefined' && __VUE_HMR_RUNTIME__.createRecord(_sfc_main.__hmrId, _sfc_main)
import.meta.hot.on('file-changed', ({ file }) => {
  __VUE_HMR_RUNTIME__.CHANGED_FILE = file
})
import.meta.hot.accept(mod => {
  if (!mod) return
  const { default: updated, _rerender_only } = mod
  if (_rerender_only) {
    __VUE_HMR_RUNTIME__.rerender(updated.__hmrId, updated.render)
  } else {
    __VUE_HMR_RUNTIME__.reload(updated.__hmrId, updated)
  }
})
export default /*#__PURE__*/_export_sfc(_sfc_main, [['render',_sfc_render]])`;
}

function transform(code: string, metadata?: ShopwareSetupHmrMeta) {
    const plugin = ShopwareSetupHmrPlugin() as unknown as {
        transform: (code: string, id: string) => { code: string } | null;
    };
    const context = {
        getModuleInfo: () => ({ meta: metadata ? { shopwareSetupHmr: metadata } : {} }),
        warn: jest.fn(),
    };

    return { result: plugin.transform.call(context, code, '/plugin/src/sw-hmr.vue.shopware-setup.vue'), warn: context.warn };
}

/** Evaluates a transformed module the way Vite does, sharing `import.meta.hot.data` with earlier versions. */
function evaluate(code: string, data: HotData, flush: () => Promise<void> = nextTick): EvaluatedModule {
    const acceptHandlers: EvaluatedModule['acceptHandlers'] = [];
    const hot: ModuleHot = {
        data,
        on: () => {},
        // Only self-accepting handlers run for an update of this module.
        accept: (callback) => typeof callback === 'function' && acceptHandlers.push(callback as AcceptHandler),
        invalidate: jest.fn(),
    };
    const body = `${code
        .replace(/^import \{ nextTick as __swNextTick \} from 'vue';$/m, '')
        .replaceAll('import.meta.hot', '__hot')
        .replace('export default', 'const __default =')}
return __default;`;
    // eslint-disable-next-line @typescript-eslint/no-implied-eval -- evaluates the module code the plugin generated
    const run = new Function('__hot', 'h', 'ref', 'computed', 'createExtendableSetup', '__swNextTick', body) as (
        ...args: unknown[]
    ) => EvaluatedModule['component'];
    const component = run(hot, h, ref, computed, createExtendableSetup, flush);

    return { component, hot, acceptHandlers };
}

describe('build/vite-plugins/shopware-setup-hmr', () => {
    const wrappers: VueWrapper[] = [];
    let name: string;
    let hmrId: string;

    function load(
        mode: ShopwareSetupHmrMeta['mode'],
        { version, structure = 'structure-1', ...output }: Version,
        data: HotData = {},
    ) {
        const { result } = transform(pluginVueOutput({ hmrId, name, ...output }), {
            componentName: name,
            mode,
            file,
            version,
            structure,
        });

        return evaluate(result!.code, data);
    }

    /** Runs the accept handlers of the loaded module with the next version, as Vite does after a save of `changedFile`. */
    function hotUpdate(current: EvaluatedModule, next: EvaluatedModule, changedFile = file) {
        runtime.CHANGED_FILE = changedFile;
        current.acceptHandlers.forEach((accept) => accept({ default: next.component }));
    }

    async function mountComponent(component: EvaluatedModule['component']) {
        const wrapper = mount(defineComponent({ render: () => h(component) }));
        wrappers.push(wrapper);
        await flushPromises();
        await wrapper.find('input').setValue('unsaved');

        return wrapper;
    }

    beforeEach(() => {
        sequence += 1;
        name = `sw-hmr-${sequence}`;
        hmrId = `swag-example-${sequence}`;
    });

    afterEach(() => {
        wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
    });

    it('rerenders a template edit and keeps the component state', async () => {
        const current = load('base', { version: 'script-1', label: 'Before', count: 1 });
        const wrapper = await mountComponent(current.component);

        hotUpdate(current, load('base', { version: 'script-1', label: 'After', count: 1 }, current.hot.data));
        await flushPromises();

        expect(wrapper.find('p').text()).toBe('After 1');
        expect(wrapper.find<HTMLInputElement>('input').element.value).toBe('unsaved');
        expect(current.hot.invalidate).not.toHaveBeenCalled();
    });

    it('remounts a component after a script edit', async () => {
        const current = load('base', { version: 'script-1', label: 'Count', count: 1 });
        const wrapper = await mountComponent(current.component);

        hotUpdate(current, load('base', { version: 'script-2', label: 'Count', count: 2 }, current.hot.data));
        await flushPromises();

        expect(wrapper.find('p').text()).toBe('Count 2');
        expect(wrapper.find<HTMLInputElement>('input').element.value).toBe('initial');
        expect(current.hot.invalidate).not.toHaveBeenCalled();
    });

    it('remounts a component after an edit of a file it imports', async () => {
        const current = load('base', { version: 'script-1', label: 'Count', count: 1 });
        const wrapper = await mountComponent(current.component);

        hotUpdate(
            current,
            load('base', { version: 'script-1', label: 'Count', count: 2 }, current.hot.data),
            '/plugin/src/use-count.ts',
        );
        await flushPromises();

        expect(wrapper.find('p').text()).toBe('Count 2');
        expect(current.hot.invalidate).not.toHaveBeenCalled();
    });

    it('reloads the page when an edit changes the structure', async () => {
        const current = load('base', { version: 'script-1', label: 'Count', count: 1 });
        const wrapper = await mountComponent(current.component);

        hotUpdate(
            current,
            load('base', { version: 'script-1', label: 'Count', count: 1, structure: 'structure-2' }, current.hot.data),
        );
        await flushPromises();

        expect(current.hot.invalidate).toHaveBeenCalled();
        expect(wrapper.find('p').text()).toBe('Count 1');
    });

    it('reloads the page when Vue fails to apply the update', async () => {
        const { result } = transform(pluginVueOutput({ hmrId, name, label: 'Count', count: 1 }), {
            componentName: name,
            mode: 'base',
            file,
            version: 'script-1',
            structure: 'structure-1',
        });
        // Vue rejects the flush promise of an update whose render throws, and leaves its scheduler broken.
        const current = evaluate(result!.code, {}, () => Promise.reject(new Error('Render failed')));
        await mountComponent(current.component);

        hotUpdate(current, load('base', { version: 'script-2', label: 'Count', count: 2 }, current.hot.data));
        await flushPromises();

        expect(current.hot.invalidate).toHaveBeenCalled();
    });

    it('reloads the page for a base that is not mounted', async () => {
        const current = load('base', { version: 'script-1', label: 'Count', count: 1 });
        const wrapper = await mountComponent(current.component);
        wrapper.unmount();

        hotUpdate(current, load('base', { version: 'script-2', label: 'Count', count: 2 }, current.hot.data));

        expect(current.hot.invalidate).toHaveBeenCalled();
    });

    [
        {
            base: 'a base that Component.extend() extends',
            compose: async (componentName: string) => {
                ComponentFactory.extend(`${componentName}-child`, componentName, {});
                await ComponentFactory.build(`${componentName}-child`);
            },
        },
        {
            base: 'a base that Component.override() overrides',
            compose: async (componentName: string) => {
                ComponentFactory.override(componentName, {});
                await ComponentFactory.build(componentName);
            },
        },
        {
            base: 'a base that extends another component',
            compose: async (componentName: string, component: ComponentConfig) => {
                component.extends = {};
                await ComponentFactory.build(componentName);
            },
        },
        {
            base: 'a base with mixins',
            compose: async (componentName: string, component: ComponentConfig) => {
                component.mixins = [{}];
                await ComponentFactory.build(componentName);
            },
        },
    ].forEach(({ base, compose }) => {
        it(`reloads the page for ${base} when the component factory composed it`, async () => {
            const current = load('base', { version: 'script-1', label: 'Count', count: 1 });
            ComponentFactory.register(name, () => Promise.resolve(current.component as ComponentConfig));
            await compose(name, current.component as ComponentConfig);
            const wrapper = await mountComponent(current.component);

            hotUpdate(current, load('base', { version: 'script-2', label: 'Count', count: 2 }, current.hot.data));
            await flushPromises();

            expect(current.hot.invalidate).toHaveBeenCalled();
            expect(wrapper.find('p').text()).toBe('Count 1');
        });
    });

    it('reloads the page on every edit when the output of @vitejs/plugin-vue is not recognised', async () => {
        const code = pluginVueOutput({ hmrId, name, label: 'Count', count: 1 }).replace(
            /import\.meta\.hot\.accept\(mod => \{[\s\S]*?\n\}\)/,
            "import.meta.hot.accept(['./other.ts'], () => {})",
        );
        const { result, warn } = transform(code, {
            componentName: name,
            mode: 'base',
            file,
            version: 'script-1',
            structure: 'structure-1',
        });
        const current = evaluate(result!.code, {});
        const wrapper = await mountComponent(current.component);

        hotUpdate(current, load('base', { version: 'script-1', label: 'After', count: 1 }, current.hot.data));
        await flushPromises();

        expect(warn).toHaveBeenCalled();
        expect(current.hot.invalidate).toHaveBeenCalled();
        expect(wrapper.find('p').text()).toBe('Count 1');
    });

    it('warns instead of failing the module when it cannot read the output of @vitejs/plugin-vue', () => {
        const { result, warn } = transform('const view = <div />;\nexport default {};', {
            componentName: name,
            mode: 'base',
            file,
            version: 'script-1',
            structure: 'structure-1',
        });

        expect(result).not.toBeNull();
        expect(warn).toHaveBeenCalled();
    });

    it('leaves modules alone that are not native setup SFCs', () => {
        expect(transform(pluginVueOutput({ hmrId, name, label: 'Count', count: 1 })).result).toBeNull();
    });
});
