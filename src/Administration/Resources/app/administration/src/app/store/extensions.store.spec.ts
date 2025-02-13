/**
 * @sw-package framework
 */

describe('extensions.store', () => {
    let store = Shopware.Store.get('extensions');

    beforeEach(() => {
        store = Shopware.Store.get('extensions');

        // Reset all entries in the store
        store.$reset();
    });

    it('has initial state', () => {
        expect(store.extensionsState).toStrictEqual({});
    });

    it('adds an extension', () => {
        store.addExtension({
            name: 'test',
            baseUrl: 'https://example.com',
            permissions: {},
            version: '1.0.0',
            type: 'app',
            integrationId: '123',
            active: true,
        });

        expect(store.extensionsState.test).toStrictEqual({
            name: 'test',
            baseUrl: 'https://example.com',
            permissions: {},
            version: '1.0.0',
            type: 'app',
            integrationId: '123',
            active: true,
        });
    });

    it('returns privilegedExtensionBaseUrls', () => {
        // @ts-expect-error
        global.activeAclRoles = ['app.exampleExtension'];

        store.addExtension({
            name: 'exampleExtension',
            baseUrl: 'https://example.com',
            permissions: {},
            version: '1.0.0',
            type: 'app',
            integrationId: '123',
            active: true,
        });

        expect(store.privilegedExtensionBaseUrls).toStrictEqual(['https://example.com']);
    });

    it('returns privilegedExtensions', () => {
        // @ts-expect-error
        global.activeAclRoles = ['app.exampleExtension'];

        store.addExtension({
            name: 'exampleExtension',
            baseUrl: 'https://example.com',
            permissions: {},
            version: '1.0.0',
            type: 'app',
            integrationId: '123',
            active: true,
        });

        expect(store.privilegedExtensions).toStrictEqual([
            {
                name: 'exampleExtension',
                baseUrl: 'https://example.com',
                permissions: {},
                version: '1.0.0',
                type: 'app',
                integrationId: '123',
                active: true,
            },
        ]);
    });
});
