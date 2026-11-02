/**
 * @sw-package framework
 */

/** A fresh registry per test, because publishing is permanent for the module's lifetime. */
async function loadRegistry() {
    jest.resetModules();

    return import('./mini-module-registry');
}

describe('src/core/mini-module-registry', () => {
    it('publishes the exports of a mini-module under its name', async () => {
        const { miniModules, publishMiniModule } = await loadRegistry();
        const format = () => 'formatted';

        publishMiniModule('sw-example', { default: 'component', format });

        expect(miniModules['sw-example']).toEqual({ default: 'component', format });
    });

    it('keeps the published exports from changing later', async () => {
        const { miniModules, publishMiniModule } = await loadRegistry();
        const exports: Record<string, unknown> = { default: 'component' };

        publishMiniModule('sw-example', exports);
        exports.added = true;

        expect(miniModules['sw-example']).toEqual({ default: 'component' });
        expect(Object.isFrozen(miniModules['sw-example'])).toBe(true);
    });

    it('refuses to publish a name twice', async () => {
        const { publishMiniModule } = await loadRegistry();
        publishMiniModule('sw-example', {});

        expect(() => publishMiniModule('sw-example', {})).toThrow('The mini-module "sw-example" is already published.');
    });
});
