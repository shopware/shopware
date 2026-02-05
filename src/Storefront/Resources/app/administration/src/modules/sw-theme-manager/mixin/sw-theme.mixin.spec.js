/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import './sw-theme.mixin';

async function createWrapper({
    aclCan = true,
    getList = null,
    repositoryOverrides = {},
} = {}) {
    const themeRepository = {
        delete: jest.fn(() => Promise.resolve()),
        create: jest.fn(() => ({ id: 'new-theme-id' })),
        save: jest.fn(() => Promise.resolve()),
        ...repositoryOverrides,
    };

    return mount({
        template: '<div></div>',
        mixins: [Shopware.Mixin.getByName('theme')],
        data() {
            return {
                isLoading: false,
                getList,
            };
        },
    }, {
        global: {
            provide: {
                repositoryFactory: {
                    create: () => themeRepository,
                },
                themeService: {},
                acl: {
                    can: jest.fn(() => aclCan),
                },
            },
            mocks: {
                $t: (key) => key,
                $router: {
                    push: jest.fn(),
                },
            },
        },
    });
}

describe('sw-theme.mixin', () => {
    it('does not open delete modal when ACL blocks', async () => {
        const wrapper = await createWrapper({ aclCan: false });

        wrapper.vm.onDeleteTheme({ id: 'theme-id' });

        expect(wrapper.vm.showDeleteModal).toBe(false);
        expect(wrapper.vm.modalTheme).toBeNull();
    });

    it('opens delete modal when ACL allows', async () => {
        const wrapper = await createWrapper();
        const theme = { id: 'theme-id' };

        wrapper.vm.onDeleteTheme(theme);

        expect(wrapper.vm.showDeleteModal).toBe(true);
        expect(wrapper.vm.modalTheme).toEqual(theme);
    });

    it('closes delete modal and clears theme', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.showDeleteModal = true;
        wrapper.vm.modalTheme = { id: 'theme-id' };

        wrapper.vm.onCloseDeleteModal();

        expect(wrapper.vm.showDeleteModal).toBe(false);
        expect(wrapper.vm.modalTheme).toBeNull();
    });

    it('confirms delete and resets modal state', async () => {
        const wrapper = await createWrapper();
        const deleteSpy = jest.spyOn(wrapper.vm, 'deleteTheme').mockImplementation(() => {});

        wrapper.vm.modalTheme = { id: 'theme-id' };
        wrapper.vm.showDeleteModal = true;

        wrapper.vm.onConfirmThemeDelete();

        expect(deleteSpy).toHaveBeenCalledWith({ id: 'theme-id' });
        expect(wrapper.vm.showDeleteModal).toBe(false);
        expect(wrapper.vm.modalTheme).toBeNull();
    });

    it('deletes theme and refreshes list when getList exists', async () => {
        const getList = jest.fn();
        const wrapper = await createWrapper({ getList });

        await wrapper.vm.deleteTheme({ id: 'theme-id' });
        await flushPromises();

        expect(getList).toHaveBeenCalled();
        expect(wrapper.vm.$router.push).not.toHaveBeenCalled();
    });

    it('deletes theme and redirects when getList is missing', async () => {
        const wrapper = await createWrapper();

        await wrapper.vm.deleteTheme({ id: 'theme-id' });
        await flushPromises();

        expect(wrapper.vm.$router.push).toHaveBeenCalledWith({ name: 'sw.theme.manager.index' });
    });

    it('shows error notification when delete fails', async () => {
        const wrapper = await createWrapper({
            repositoryOverrides: {
                delete: jest.fn(() => Promise.reject(new Error('fail'))),
            },
        });
        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.vm.deleteTheme({ id: 'theme-id' });
        await flushPromises();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            title: 'sw-theme-manager.components.themeListItem.notificationDeleteErrorTitle',
            message: 'sw-theme-manager.components.themeListItem.notificationDeleteErrorMessage',
        });
    });

    it('opens duplicate modal when ACL allows', async () => {
        const wrapper = await createWrapper();
        const theme = { id: 'theme-id' };

        wrapper.vm.onDuplicateTheme(theme);

        expect(wrapper.vm.showDuplicateModal).toBe(true);
        expect(wrapper.vm.modalTheme).toEqual(theme);
    });

    it('does not open duplicate modal when ACL blocks', async () => {
        const wrapper = await createWrapper({ aclCan: false });

        wrapper.vm.onDuplicateTheme({ id: 'theme-id' });

        expect(wrapper.vm.showDuplicateModal).toBe(false);
    });

    it('closes duplicate modal and resets state', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.showDuplicateModal = true;
        wrapper.vm.modalTheme = { id: 'theme-id' };
        wrapper.vm.newThemeName = 'New name';

        wrapper.vm.onCloseDuplicateModal();

        expect(wrapper.vm.showDuplicateModal).toBe(false);
        expect(wrapper.vm.modalTheme).toBeNull();
        expect(wrapper.vm.newThemeName).toBe('');
    });

    it('confirms duplicate and resets modal state', async () => {
        const wrapper = await createWrapper();
        const duplicateSpy = jest.spyOn(wrapper.vm, 'duplicateTheme').mockImplementation(() => {});

        wrapper.vm.modalTheme = { id: 'theme-id' };
        wrapper.vm.newThemeName = 'New name';

        wrapper.vm.onConfirmThemeDuplicate();

        expect(duplicateSpy).toHaveBeenCalledWith({ id: 'theme-id' }, 'New name');
        expect(wrapper.vm.showDuplicateModal).toBe(false);
        expect(wrapper.vm.modalTheme).toBeNull();
        expect(wrapper.vm.newThemeName).toBe('');
    });

    it('duplicates theme and redirects to detail', async () => {
        const wrapper = await createWrapper();
        const parentTheme = {
            id: 'parent-id',
            author: 'author',
            description: 'description',
            labels: { foo: 'bar' },
            helpTexts: { baz: 'qux' },
            customFields: { custom: true },
            previewMediaId: 'media-id',
        };

        await wrapper.vm.duplicateTheme(parentTheme, 'New theme');
        await flushPromises();

        expect(wrapper.vm.themeRepository.save).toHaveBeenCalledWith(expect.objectContaining({
            name: 'New theme',
            parentThemeId: 'parent-id',
            author: 'author',
            description: 'description',
            previewMediaId: 'media-id',
            active: true,
        }), Shopware.Context.api);
        expect(wrapper.vm.$router.push).toHaveBeenCalledWith({
            name: 'sw.theme.manager.detail',
            params: { id: 'new-theme-id' },
        });
    });

    it('opens rename modal with current theme name', async () => {
        const wrapper = await createWrapper();
        const theme = { id: 'theme-id', name: 'Old name' };

        wrapper.vm.onRenameTheme(theme);

        expect(wrapper.vm.showRenameModal).toBe(true);
        expect(wrapper.vm.modalTheme).toEqual(theme);
        expect(wrapper.vm.newThemeName).toBe('Old name');
    });

    it('does not open rename modal when ACL blocks', async () => {
        const wrapper = await createWrapper({ aclCan: false });

        wrapper.vm.onRenameTheme({ id: 'theme-id' });

        expect(wrapper.vm.showRenameModal).toBe(false);
    });

    it('closes rename modal and resets state', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.showRenameModal = true;
        wrapper.vm.modalTheme = { id: 'theme-id' };
        wrapper.vm.newThemeName = 'New name';

        wrapper.vm.onCloseRenameModal();

        expect(wrapper.vm.showRenameModal).toBe(false);
        expect(wrapper.vm.modalTheme).toBeNull();
        expect(wrapper.vm.newThemeName).toBe('');
    });

    it('confirms rename and resets modal state', async () => {
        const wrapper = await createWrapper();
        const renameSpy = jest.spyOn(wrapper.vm, 'RenameTheme').mockImplementation(() => {});

        wrapper.vm.modalTheme = { id: 'theme-id' };
        wrapper.vm.newThemeName = 'New name';

        wrapper.vm.onConfirmThemeRename();

        expect(renameSpy).toHaveBeenCalledWith({ id: 'theme-id' }, 'New name');
        expect(wrapper.vm.showRenameModal).toBe(false);
        expect(wrapper.vm.modalTheme).toBeNull();
        expect(wrapper.vm.newThemeName).toBe('');
    });

    it('renames theme and saves', async () => {
        const wrapper = await createWrapper();
        const theme = { id: 'theme-id', name: 'Old name' };

        wrapper.vm.RenameTheme(theme, 'New name');

        expect(theme.name).toBe('New name');
        expect(wrapper.vm.themeRepository.save).toHaveBeenCalledWith(theme, Shopware.Context.api);
    });

    it('saves theme even if name is empty', async () => {
        const wrapper = await createWrapper();
        const theme = { id: 'theme-id', name: 'Old name' };

        wrapper.vm.RenameTheme(theme, '');

        expect(theme.name).toBe('Old name');
        expect(wrapper.vm.themeRepository.save).toHaveBeenCalledWith(theme, Shopware.Context.api);
    });
});
