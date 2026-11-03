import { addServiceName, exportRegisteredName, exportServiceNames } from './outside-edits';

describe('outside edits', () => {
    it('adds a service name constant in order and exports it from the index', () => {
        // ARRANGE
        const names = `/**
 * @sw-package framework
 */

/**
 * Creates the repositories for entities.
 *
 * @public
 */
export const repositoryFactory = 'repositoryFactory';
`;

        // ACT
        const added = addServiceName(addServiceName(names, 'acl'), 'syncService');
        const index = exportServiceNames("export { repositoryFactory } from './src/service-names';\n", [
            'syncService',
            'acl',
        ]);

        // ASSERT
        expect(added.indexOf("export const acl = 'acl';")).toBeLessThan(added.indexOf('Creates the repositories'));
        expect(added.trimEnd().endsWith("export const syncService = 'syncService';")).toBe(true);
        expect(added).toContain(' * The name the acl service is registered and injected under.\n');
        expect(index).toBe("export { acl, repositoryFactory, syncService } from './src/service-names';\n");
    });

    it('exports the id of a store and the name of a filter above their registration and its doc comment', () => {
        // ARRANGE
        const store = "const store = Shopware.Store.register({\n    id: 'swProductDetail',\n});\n";
        const filter = "/**\n * @private\n */\nShopware.Filter.register('date', (value) => value);\n";

        // ACT
        const edited = [
            exportRegisteredName(store, 'Store', 'swProductDetail', 'swProductDetailStoreId'),
            exportRegisteredName(filter, 'Filter', 'date', 'dateFilterName'),
        ];

        // ASSERT
        expect(edited[0]).toBe(`/**
 * @private
 * The id the store is registered under, for code that looks it up with \`Shopware.Store.get()\`.
 */
export const swProductDetailStoreId = 'swProductDetail';

const store = Shopware.Store.register({
    id: swProductDetailStoreId,
});
`);
        expect(edited[1]).toContain(
            "export const dateFilterName = 'date';\n\n/**\n * @private\n */\nShopware.Filter.register(dateFilterName, (value) => value);",
        );
    });
});
