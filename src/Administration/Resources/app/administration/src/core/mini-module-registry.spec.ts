/**
 * @sw-package framework
 */

/** A fresh registry per test, because publishing is permanent for the module's lifetime. */
async function loadRegistry() {
    jest.resetModules();

    return import('./mini-module-registry');
}

describe('src/core/mini-module-registry', () => {
    it('publishes the exports of a mini-module under its kind and name', async () => {
        const { publicModules, publish } = await loadRegistry();
        const format = () => 'formatted';

        publish('components', 'sw-example', { default: 'component', format });

        expect(publicModules.components['sw-example']).toEqual({ default: 'component', format });
        expect(publicModules.helpers).toEqual({});
    });

    it('keeps the published exports from changing later', async () => {
        const { publicModules, publish } = await loadRegistry();
        const exports: Record<string, unknown> = { default: 'component' };

        publish('components', 'sw-example', exports);
        exports.added = true;

        expect(publicModules.components['sw-example']).toEqual({ default: 'component' });
        expect(Object.isFrozen(publicModules.components['sw-example'])).toBe(true);
    });

    it('refuses to publish a name twice within a kind', async () => {
        const { publish } = await loadRegistry();
        publish('components', 'sw-example', {});

        expect(() => publish('components', 'sw-example', {})).toThrow(
            'The mini-module "components/sw-example" is already published.',
        );
        expect(() => publish('helpers', 'sw-example', {})).not.toThrow();
    });

    it('refuses a kind it does not know', async () => {
        const { publish } = await loadRegistry();

        // @ts-expect-error The kind is checked at runtime, too.
        expect(() => publish('nope', 'sw-example', {})).toThrow('"nope" is not a public kind.');
    });
});
