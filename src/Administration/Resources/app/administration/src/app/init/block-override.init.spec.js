/**
 * @package customer-order
 */
import initializeBlockOverride from 'src/app/init/block-override.init';

describe('src/app/init/block-override.init.ts', () => {
    it('should register the store', async () => {
        initializeBlockOverride();
        const store = Shopware.Store.get('blockOverrideState');

        expect(store.blocks).toStrictEqual({});
    });
});
