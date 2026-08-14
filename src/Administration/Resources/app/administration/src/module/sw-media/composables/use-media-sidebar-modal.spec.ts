/**
 * @sw-package discovery
 */
import { nextTick } from 'vue';
import { useMediaSidebarModal } from './use-media-sidebar-modal';

function createComposable(
    privileges: string[] = [
        'media.editor',
        'media.deleter',
    ],
) {
    jest.spyOn(Shopware, 'Service').mockImplementation(
        () =>
            ({
                can: (privilege: string) => privileges.includes(privilege),
            }) as never,
    );

    const options = {
        onItemsDelete: jest.fn(),
        onFolderItemsDissolve: jest.fn(),
        onItemsMove: jest.fn(),
    };

    return { options, composable: useMediaSidebarModal(options) };
}

describe('src/module/sw-media/composables/use-media-sidebar-modal', () => {
    afterEach(() => {
        jest.restoreAllMocks();
    });

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
    ] as const)('%s opens %s only with the %s privilege', (open, flag, privilege) => {
        const denied = createComposable([]).composable;
        denied[open]();
        expect(denied[flag].value).toBe(false);

        const granted = createComposable([privilege]).composable;
        granted[open]();
        expect(granted[flag].value).toBe(true);
    });

    it('opens the folder settings without an acl check', () => {
        const { composable } = createComposable([]);

        composable.openFolderSettings();
        expect(composable.showFolderSettings.value).toBe(true);

        composable.closeFolderSettings();
        expect(composable.showFolderSettings.value).toBe(false);
    });

    it.each([
        [
            'deleteSelectedItems',
            'showModalDelete',
            'openModalDelete',
            'onItemsDelete',
        ],
        [
            'onFolderDissolved',
            'showFolderDissolve',
            'openFolderDissolve',
            'onFolderItemsDissolve',
        ],
        [
            'onFolderMoved',
            'showModalMove',
            'openModalMove',
            'onItemsMove',
        ],
    ] as const)('%s closes %s and forwards the payload on the next tick', async (handler, flag, open, callback) => {
        const { options, composable } = createComposable();
        const payload = { mediaIds: ['media-1'] };

        composable[open]();
        composable[handler](payload);

        expect(composable[flag].value).toBe(false);
        expect(options[callback]).not.toHaveBeenCalled();

        await nextTick();

        expect(options[callback]).toHaveBeenCalledWith(payload);
    });
});
