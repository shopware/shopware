/**
 * @package buyers-experience
 */
import './index';


describe('module/sw-cms/blocks/app/app-renderer/index', () => {
    it('should register the components', async () => {
        const registry = Shopware.Component.getComponentRegistry();

        expect(registry.has('sw-cms-block-app-preview-renderer')).toBeTruthy();
        expect(registry.has('sw-cms-block-app-renderer')).toBeTruthy();
    });
});
