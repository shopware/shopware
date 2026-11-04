import { routeForSpec } from '../fake-index';
import { rewriteStore } from './store';

describe('rewriteStore', () => {
    it('looks a store up through a function in dependencies.ts and exports the id from the store file', () => {
        // ARRANGE
        const source = `export default {
    computed: {
        product() {
            return Shopware.Store.get('swProductDetail').product;
        },
    },
};
`;

        // ACT
        const { code, dependencies, outsideEdits } = routeForSpec(source, { use: [rewriteStore] });

        // ASSERT
        expect(code).toBe(`import { useProductDetailStore } from '../dependencies';

export default {
    computed: {
        product() {
            return useProductDetailStore().product;
        },
    },
};
`);
        expect(dependencies).toBe(`import { swProductDetailStoreId } from '../../page/sw-product-detail/store';

/** @private */
export const useProductDetailStore = () => Shopware.Store.get(swProductDetailStoreId);
`);
        expect(outsideEdits).toEqual([
            { kind: 'store-id', file: '/admin/store.ts', id: 'swProductDetail', exportName: 'swProductDetailStoreId' },
        ]);
    });

    it('uses the id a store file exports already, and leaves a store looked up by a variable', () => {
        // ARRANGE
        const source = `export default {
    created() {
        Shopware.Store.get('notification').createNotification({});
        Shopware.Store.get(this.storeName).reset();
    },
};
`;

        // ACT
        const { code, dependencies, outsideEdits } = routeForSpec(source, { use: [rewriteStore] });

        // ASSERT
        expect(code).toContain('useNotificationStore().createNotification({});');
        expect(code).toContain('Shopware.Store.get(this.storeName).reset();');
        expect(dependencies).toContain('export const useNotificationStore = () => Shopware.Store.get(notificationStoreId);');
        expect(outsideEdits).toEqual([]);
    });

    it('re-exports the use function of a store mini-module', () => {
        // ARRANGE
        const source = `export default {
    computed: {
        category() {
            return Shopware.Store.get('swCategoryDetail').category;
        },
    },
};
`;

        // ACT
        const { code, dependencies, outsideEdits } = routeForSpec(source, { use: [rewriteStore] });

        // ASSERT
        expect(code).toContain('return useCategoryDetailStore().category;');
        expect(dependencies).toBe(`/** @private */
export { useSwCategoryDetailStore as useCategoryDetailStore } from '../../page/sw-category-detail/store';
`);
        expect(outsideEdits).toEqual([]);
    });
});
