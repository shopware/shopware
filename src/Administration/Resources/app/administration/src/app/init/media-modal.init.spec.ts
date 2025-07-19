/**
 * @sw-package framework
 */
import initializeMediaModal from 'src/app/init/media-modal.init';
import { ui } from '@shopware-ag/meteor-admin-sdk';

const mediaModalConfig = {
    allowMultiSelect: false,
    fileAccept: 'image/*',
    callback: () => {},
} as const;

describe('src/app/init/media-modal.init.ts', () => {
    beforeAll(() => {
        initializeMediaModal();
    });

    beforeEach(() => {
        Shopware.Store.get('mediaModal').mediaModal = null;
    });

    it('should handle incoming uiMediaModalOpen requests', async () => {
        await ui.mediaModal.open(mediaModalConfig);

        const mediaModal = Shopware.Store.get('mediaModal').mediaModal;
        expect(mediaModal?.allowMultiSelect).toBe(mediaModalConfig.allowMultiSelect);
        expect(mediaModal?.fileAccept).toBe(mediaModalConfig.fileAccept);
        expect(typeof mediaModal?.callback).toBe('function');
    });
});
