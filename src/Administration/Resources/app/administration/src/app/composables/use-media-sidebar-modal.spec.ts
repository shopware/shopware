/**
 * @sw-package discovery
 */
import { nextTick } from 'vue';
import useMediaSidebarModal from './use-media-sidebar-modal';

function stubShopware(privileges: string[]): void {
    window.Shopware = {
        Service: jest.fn(() => ({ can: (privilege: string) => privileges.includes(privilege) })),
    } as unknown as typeof Shopware;
}

function options(): {
    onItemsDelete: jest.Mock;
    onFolderItemsDissolve: jest.Mock;
    onItemsMove: jest.Mock;
} {
    return {
        onItemsDelete: jest.fn(),
        onFolderItemsDissolve: jest.fn(),
        onItemsMove: jest.fn(),
    };
}

describe('src/app/composables/use-media-sidebar-modal', () => {
    it.each([
        [
            'openModalReplace',
            'showModalReplace',
            'media.editor',
        ],
        [
            'openModalDelete',
            'showModalDelete',
            'media.deleter',
        ],
        [
            'openFolderDissolve',
            'showFolderDissolve',
            'media.editor',
        ],
        [
            'openModalMove',
            'showModalMove',
            'media.editor',
        ],
    ])('%s opens its modal only with the %s privilege', (open, flag, privilege) => {
        stubShopware([]);
        const denied = useMediaSidebarModal(options()) as unknown as Record<string, (() => void) & { value: boolean }>;

        denied[open]();

        expect(denied[flag].value).toBe(false);

        stubShopware([privilege]);
        const granted = useMediaSidebarModal(options()) as unknown as Record<string, (() => void) & { value: boolean }>;

        granted[open]();

        expect(granted[flag].value).toBe(true);
    });

    it('opens and closes the folder settings without a privilege', () => {
        stubShopware([]);
        const { showFolderSettings, openFolderSettings, closeFolderSettings } = useMediaSidebarModal(options());

        openFolderSettings();

        expect(showFolderSettings.value).toBe(true);

        closeFolderSettings();

        expect(showFolderSettings.value).toBe(false);
    });

    it.each([
        [
            'deleteSelectedItems',
            'showModalDelete',
            'onItemsDelete',
        ],
        [
            'onFolderDissolved',
            'showFolderDissolve',
            'onFolderItemsDissolve',
        ],
        [
            'onFolderMoved',
            'showModalMove',
            'onItemsMove',
        ],
    ])('%s closes its modal and reports the ids a tick later', async (handler, flag, callback) => {
        stubShopware([
            'media.editor',
            'media.deleter',
        ]);
        const callbacks = options();
        const composable = useMediaSidebarModal(callbacks) as unknown as Record<
            string,
            ((ids: string[]) => void) & { value: boolean }
        >;

        composable[flag].value = true;
        composable[handler](['id-1']);

        expect(composable[flag].value).toBe(false);
        expect(callbacks[callback as keyof typeof callbacks]).not.toHaveBeenCalled();

        await nextTick();

        expect(callbacks[callback as keyof typeof callbacks]).toHaveBeenCalledWith(['id-1']);
    });
});
