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
        Shopware.Store.get('extensions').extensionsState = {};
    });

    function registerExtension(baseUrl: string): void {
        Shopware.Store.get('extensions').addExtension({
            name: 'TestApp',
            baseUrl,
            permissions: {},
            version: '1.0.0',
            type: 'app',
            integrationId: '123' as EntityKey<'integration'>,
            active: true,
        });
    }

    it('should handle incoming uiMediaModalOpen requests', async () => {
        await ui.mediaModal.open(mediaModalConfig);

        const mediaModal = Shopware.Store.get('mediaModal').mediaModal;
        expect(mediaModal?.allowMultiSelect).toBe(mediaModalConfig.allowMultiSelect);
        expect(mediaModal?.fileAccept).toBe(mediaModalConfig.fileAccept);
        expect(typeof mediaModal?.callback).toBe('function');
    });

    it('should handle incoming uiMediaModalOpenSaveMedia requests', async () => {
        await ui.mediaModal.openSaveMedia({
            initialFolderId: 'folderId',
            initialFileName: 'Media name',
            fileType: 'png',
            callback: () => {},
        });

        const saveMediaModal = Shopware.Store.get('mediaModal').saveMediaModal;
        expect(saveMediaModal?.initialFolderId).toBe('folderId');
        expect(saveMediaModal?.initialFileName).toBe('Media name');
        expect(saveMediaModal?.fileType).toBe('png');
        expect(typeof saveMediaModal?.callback).toBe('function');
    });

    it('should keep the base url of the app that opens the media modal for its tabs', async () => {
        registerExtension(`${window.location.origin}/app`);

        await ui.mediaModal.open({
            ...mediaModalConfig,
            tabs: [{ label: 'Primitives', locationId: 'primitives-tab' }],
        });

        const mediaModal = Shopware.Store.get('mediaModal').mediaModal;
        expect(mediaModal?.tabs).toEqual([{ label: 'Primitives', locationId: 'primitives-tab' }]);
        expect(mediaModal?.baseUrl).toBe(`${window.location.origin}/app`);
    });

    describe('uiMediaModalSelect', () => {
        type SelectHandler = (message: { selection: unknown[] }, meta: { _event_: { origin: string } }) => void;

        // The select handler, called with an explicit message origin (the SDK sends none in Jest).
        function selectHandler(): SelectHandler {
            const handlers: Record<string, unknown> = {};
            const handle = jest.spyOn(Shopware.ExtensionAPI, 'handle').mockImplementation(((
                type: string,
                method: unknown,
            ) => {
                handlers[type] = method;
                return () => {};
            }) as unknown as typeof Shopware.ExtensionAPI.handle);
            initializeMediaModal();
            handle.mockRestore();

            return handlers.uiMediaModalSelect as SelectHandler;
        }

        function openModalOf(baseUrl: string | undefined, callback = jest.fn()): jest.Mock {
            Shopware.Store.get('mediaModal').openModal({ ...mediaModalConfig, callback, baseUrl });
            return callback;
        }

        it('should pass the selection of an app tab to the callback and close the media modal', () => {
            const callback = openModalOf('https://app.example/admin');

            selectHandler()({ selection: [{ id: 'cube' }] }, { _event_: { origin: 'https://app.example' } });

            expect(callback).toHaveBeenCalledWith([{ id: 'cube' }]);
            expect(Shopware.Store.get('mediaModal').mediaModal).toBeNull();
        });

        it('should ignore a selection from another app than the one that opened the media modal', () => {
            const callback = openModalOf('https://app.example/admin');

            selectHandler()({ selection: [{ id: 'cube' }] }, { _event_: { origin: 'https://other-app.example' } });

            expect(callback).not.toHaveBeenCalled();
            expect(Shopware.Store.get('mediaModal').mediaModal).not.toBeNull();
        });

        it('should ignore a selection from an origin that is only a prefix of the app origin', () => {
            const callback = openModalOf('https://app.example.evil/admin');

            selectHandler()({ selection: [{ id: 'cube' }] }, { _event_: { origin: 'https://app.example' } });

            expect(callback).not.toHaveBeenCalled();
        });

        it('should ignore a selection of a media modal opened without a known app', () => {
            const callback = openModalOf(undefined);

            selectHandler()({ selection: [{ id: 'cube' }] }, { _event_: { origin: '' } });

            expect(callback).not.toHaveBeenCalled();
            expect(Shopware.Store.get('mediaModal').mediaModal).not.toBeNull();
        });

        it('should ignore a selection when no media modal is open', () => {
            selectHandler()({ selection: [] }, { _event_: { origin: 'https://app.example' } });

            expect(Shopware.Store.get('mediaModal').mediaModal).toBeNull();
        });

        it('should be reachable through ui.mediaModal.select', async () => {
            const callback = openModalOf(undefined);

            await ui.mediaModal.select({ selection: [{ id: 'cube' }] });

            // The SDK sends no origin in Jest, so the selection is ignored without an error.
            expect(callback).not.toHaveBeenCalled();
        });
    });
});
