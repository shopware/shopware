/**
 * @package buyers-experience
 */
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

describe('module/sw-cms/blocks/app/app-renderer/index', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
        await import('./index');
    });

    it('should register the components', async () => {
        const registry = Shopware.Component.getComponentRegistry();

        expect(registry.has('sw-cms-block-app-renderer')).toBeTruthy();
        expect(registry.has('sw-cms-block-app-preview-renderer')).toBeTruthy();
    });
});
