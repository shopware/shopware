import initComponents from 'src/app/init/component.init';

describe('src/app/init/component.init.ts', () => {
    let baseComponents;

    beforeAll(async () => {
        baseComponents = await initComponents();
    });

    it('should init async components', () => {
        expect(Shopware.Component.getComponentRegistry().get('sw-code-editor') !== undefined).toBe(true);
        expect(Shopware.Component.getComponentRegistry().get('sw-datepicker') !== undefined).toBe(true);
        expect(Shopware.Component.getComponentRegistry().get('sw-chart') !== undefined).toBe(true);
        expect(Shopware.Component.getComponentRegistry().get('sw-image-slider') !== undefined).toBe(true);
        expect(Shopware.Component.getComponentRegistry().get('sw-media-add-thumbnail-form') !== undefined).toBe(true);
        expect(Shopware.Component.getComponentRegistry().get('sw-media-base-item') !== undefined).toBe(true);
        expect(Shopware.Component.getComponentRegistry().get('sw-media-compact-upload-v2') !== undefined).toBe(true);
        expect(Shopware.Component.getComponentRegistry().get('sw-media-entity-mapper') !== undefined).toBe(true);
        expect(Shopware.Component.getComponentRegistry().get('sw-media-folder-content') !== undefined).toBe(true);
        expect(Shopware.Component.getComponentRegistry().get('sw-media-folder-item') !== undefined).toBe(true);
        expect(Shopware.Component.getComponentRegistry().get('sw-media-list-selection-item-v2') !== undefined).toBe(true);
        expect(Shopware.Component.getComponentRegistry().get('sw-media-list-selection-v2') !== undefined).toBe(true);
        expect(Shopware.Component.getComponentRegistry().get('sw-media-media-item') !== undefined).toBe(true);
        expect(Shopware.Component.getComponentRegistry().get('sw-media-modal-delete') !== undefined).toBe(true);
        expect(Shopware.Component.getComponentRegistry().get('sw-media-modal-folder-dissolve') !== undefined).toBe(true);
        expect(Shopware.Component.getComponentRegistry().get('sw-media-modal-folder-settings') !== undefined).toBe(true);
        expect(Shopware.Component.getComponentRegistry().get('sw-media-modal-move') !== undefined).toBe(true);
        expect(Shopware.Component.getComponentRegistry().get('sw-media-modal-replace') !== undefined).toBe(true);
        expect(Shopware.Component.getComponentRegistry().get('sw-media-preview-v2') !== undefined).toBe(true);
        expect(Shopware.Component.getComponentRegistry().get('sw-media-replace') !== undefined).toBe(true);
        expect(Shopware.Component.getComponentRegistry().get('sw-media-upload-v2') !== undefined).toBe(true);
        expect(Shopware.Component.getComponentRegistry().get('sw-media-url-form') !== undefined).toBe(true);
        expect(Shopware.Component.getComponentRegistry().get('sw-sidebar-media-item') !== undefined).toBe(true);
        expect(Shopware.Component.getComponentRegistry().get('sw-extension-icon') !== undefined).toBe(true);
    });

    it('should init base components', () => {
        expect(baseComponents).toBeInstanceOf(Array);
        expect(baseComponents.length).toBeGreaterThan(0);
    });
});
