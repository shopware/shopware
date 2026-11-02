// @vitest-environment node
import { describe, expect, it, vi } from 'vitest';
import type { EnvironmentModuleNode } from 'vite';
import { COMPONENT_HMR_FOOTER, componentHmrPlugin } from './component-hmr-plugin';

const STOREFRONT_ROOT = '/project/src/Storefront/Resources/app/storefront';
const COMPONENT = '/project/custom/plugins/Demo/src/Resources/views/components/Demo/Counter/index.ts';

type Hooks = {
    transform: (this: unknown, code: string, id: string) => { code: string } | undefined,
    hotUpdate: (this: unknown, options: { modules: EnvironmentModuleNode[] }) => unknown,
};

function createContext(name = 'client') {
    return { environment: { name, hot: { send: vi.fn() } } };
}

function moduleNode(file: string, importers: EnvironmentModuleNode[] = []): EnvironmentModuleNode {
    return { file, importers: new Set(importers) } as unknown as EnvironmentModuleNode;
}

describe('build/vite/component-hmr-plugin', () => {
    const plugin = componentHmrPlugin(STOREFRONT_ROOT) as unknown as Hooks;

    it('appends the HMR handler to component scripts in the browser', () => {
        expect(plugin.transform.call(createContext(), 'export default class {}', `${COMPONENT}?t=1`)).toEqual({
            code: `export default class {}\n${COMPONENT_HMR_FOOTER}`,
            map: null,
        });
        expect(plugin.transform.call(createContext(), 'export {}', '/project/src/Storefront/Resources/views/components/Sw/Foo.js'))
            .toBeDefined();
    });

    it('leaves other modules, tests, type declarations and server environments alone', () => {
        const ids = [
            '/project/src/Storefront/Resources/app/storefront/src/main.js',
            '/project/src/Storefront/Resources/views/components/Sw/Foo.scss',
            '/project/src/Storefront/Resources/views/components/Sw/Foo.test.ts',
            '/project/src/Storefront/Resources/views/components/Sw/Foo.d.ts',
        ];

        ids.forEach((id) => expect(plugin.transform.call(createContext(), 'export {}', id)).toBeUndefined());
        expect(plugin.transform.call(createContext('ssr'), 'export {}', COMPONENT)).toBeUndefined();
    });

    it('reloads the page when the component system changes, because the components share its instance', () => {
        const runtime = moduleNode(`${STOREFRONT_ROOT}/src/shopware.ts`, [moduleNode(COMPONENT)]);
        const componentBase = moduleNode(`${STOREFRONT_ROOT}/src/component-system/component.ts`, [runtime]);
        const context = createContext();

        expect(plugin.hotUpdate.call(context, { modules: [componentBase] })).toEqual([]);
        expect(context.environment.hot.send).toHaveBeenCalledWith({ type: 'full-reload' });
    });

    it('lets the components accept changes of their own modules and helpers', () => {
        const component = moduleNode(COMPONENT);
        const helper = moduleNode('/project/custom/plugins/Demo/src/Resources/views/components/Demo/Counter/format.ts', [component]);
        const context = createContext();

        expect(plugin.hotUpdate.call(context, { modules: [helper] })).toBeUndefined();
        expect(context.environment.hot.send).not.toHaveBeenCalled();
    });
});
