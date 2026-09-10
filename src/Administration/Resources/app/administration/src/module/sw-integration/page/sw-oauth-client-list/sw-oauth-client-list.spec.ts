/**
 * @sw-package framework
 */
import { shallowMount, flushPromises } from '@vue/test-utils';

const client = {
    id: '0123456789abcdef0123456789abcdef',
    name: 'Desktop tool',
    active: true,
    redirectUris: ['https://example.com/callback'],
};
type PageVm = {
    currentClient: typeof client | null;
    deleteClient: typeof client | null;
    redirectUrisText: string;
    isNew: boolean;
    isLoading: boolean;
    isSaving: boolean;
    canSave: boolean;
    onCreate: () => void;
    onEdit: (value: typeof client) => Promise<void>;
    onSave: () => Promise<void>;
    onClose: () => void;
    onDelete: () => Promise<void>;
    onPageChange: (value: { page: number; limit: number }) => void;
    createNotificationError: (value: { message: string }) => void;
};

async function createWrapper(
    privileges = [
        'oauth_client.viewer',
        'oauth_client.creator',
        'oauth_client.editor',
        'oauth_client.deleter',
    ],
) {
    const repository = {
        search: jest.fn().mockResolvedValue(Object.assign([], { total: 0 })),
        get: jest.fn().mockImplementation(() => Promise.resolve({ ...client })),
        create: jest.fn().mockImplementation(() => ({ ...client, name: '', redirectUris: [] })),
        save: jest.fn().mockResolvedValue(undefined),
        delete: jest.fn().mockResolvedValue(undefined),
    };
    const wrapper = shallowMount(await wrapTestComponent('sw-oauth-client-list', { sync: true }), {
        global: {
            provide: {
                repositoryFactory: { create: () => repository },
                acl: { can: (privilege: string) => privileges.includes(privilege) },
            },
            stubs: {
                'sw-page': { template: '<main><slot name="smart-bar-actions"/><slot name="content"/></main>' },
                'sw-card-view': { template: '<div><slot/></div>' },
                'mt-card': { template: '<div><slot/></div>' },
                'mt-empty-state': { template: '<section data-testid="empty-state" />' },
                'sw-data-grid': { template: '<table data-testid="application-grid" />' },
                'sw-pagination': { template: '<nav data-testid="pagination" />' },
                'sw-modal': { template: '<div><slot/><slot name="modal-footer"/></div>' },
            },
        },
    });
    await flushPromises();
    return { wrapper, vm: wrapper.vm as unknown as PageVm, repository };
}

describe('OAuth application management', () => {
    let page: Awaited<ReturnType<typeof createWrapper>>;

    afterEach(() => {
        page?.wrapper.unmount();
        jest.restoreAllMocks();
    });

    it('creates a public application and reloads after saving', async () => {
        page = await createWrapper();
        page.vm.onCreate();
        expect(page.vm.currentClient?.active).toBe(true);
        page.vm.currentClient!.name = 'Desktop tool';
        page.vm.redirectUrisText = 'https://example.com/Callback?x=%2f\nhttp://127.0.0.1/callback';
        await page.vm.onSave();

        expect(page.repository.save).toHaveBeenCalledWith(
            expect.objectContaining({
                name: 'Desktop tool',
                active: true,
                redirectUris: [
                    'https://example.com/Callback?x=%2f',
                    'http://127.0.0.1/callback',
                ],
            }),
            Shopware.Context.api,
        );
        expect(page.repository.get).toHaveBeenCalledWith(client.id, Shopware.Context.api);
        expect(page.repository.search).toHaveBeenCalledTimes(2);
        expect(page.vm.currentClient).toBeNull();
    });

    it('shows a useful empty state instead of an empty table', async () => {
        page = await createWrapper();
        expect(page.wrapper.find('[data-testid="empty-state"]').exists()).toBe(true);
        expect(page.wrapper.find('[data-testid="application-grid"]').exists()).toBe(false);
        expect(page.wrapper.find('[data-testid="pagination"]').exists()).toBe(false);
    });

    it('shows the table and pagination for a populated multi-page result', async () => {
        page = await createWrapper();
        page.repository.search.mockResolvedValueOnce(Object.assign([{ ...client }], { total: 26 }));
        page.vm.onPageChange({ page: 1, limit: 25 });
        await flushPromises();
        expect(page.wrapper.find('[data-testid="empty-state"]').exists()).toBe(false);
        expect(page.wrapper.find('[data-testid="application-grid"]').exists()).toBe(true);
        expect(page.wrapper.find('[data-testid="pagination"]').exists()).toBe(true);
    });

    it('keeps edits isolated until saved', async () => {
        page = await createWrapper();
        await page.vm.onEdit(client);
        page.vm.currentClient!.name = 'Changed';
        page.vm.onClose();
        expect(client.name).toBe('Desktop tool');
        expect(page.repository.save).not.toHaveBeenCalled();
    });

    it('allows a creator without editor privileges to create only', async () => {
        page = await createWrapper([
            'oauth_client.viewer',
            'oauth_client.creator',
        ]);
        page.vm.onCreate();
        expect(page.vm.canSave).toBe(true);
        await page.vm.onEdit(client);
        expect(page.vm.canSave).toBe(false);
        await page.vm.onSave();
        expect(page.repository.save).not.toHaveBeenCalled();
    });

    it('does not let a viewer create, update or delete', async () => {
        page = await createWrapper(['oauth_client.viewer']);
        page.vm.onCreate();
        expect(page.repository.create).not.toHaveBeenCalled();
        await page.vm.onEdit(client);
        expect(page.vm.canSave).toBe(false);
        await page.vm.onSave();
        page.vm.deleteClient = client;
        await page.vm.onDelete();
        expect(page.repository.save).not.toHaveBeenCalled();
        expect(page.repository.delete).not.toHaveBeenCalled();
    });

    it('preserves input and reports failed saves', async () => {
        page = await createWrapper();
        const notify = jest.spyOn(page.vm, 'createNotificationError');
        page.vm.onCreate();
        page.vm.redirectUrisText = ' http://invalid.example';
        page.repository.save.mockRejectedValueOnce(new Error('Invalid redirect URL'));
        await page.vm.onSave();
        expect(page.vm.currentClient).not.toBeNull();
        expect(page.vm.redirectUrisText).toBe(' http://invalid.example');
        expect(page.vm.isSaving).toBe(false);
        expect(notify).toHaveBeenCalled();
    });

    it('deletes only the confirmed application and reloads', async () => {
        page = await createWrapper();
        page.vm.deleteClient = client;
        await page.vm.onDelete();
        expect(page.repository.delete).toHaveBeenCalledWith(client.id, Shopware.Context.api);
        expect(page.vm.deleteClient).toBeNull();
        expect(page.repository.search).toHaveBeenCalledTimes(2);
    });

    it('retains the confirmation when deletion fails', async () => {
        page = await createWrapper();
        page.vm.deleteClient = client;
        page.repository.delete.mockRejectedValueOnce(new Error('Network failure'));
        const notify = jest.spyOn(page.vm, 'createNotificationError');
        await page.vm.onDelete();
        expect(page.vm.deleteClient).not.toBeNull();
        expect(page.vm.isSaving).toBe(false);
        expect(notify).toHaveBeenCalled();
    });

    it('loads the selected page and reports load failures', async () => {
        page = await createWrapper();
        const notify = jest.spyOn(page.vm, 'createNotificationError');
        page.repository.search.mockRejectedValueOnce(new Error('Network failure'));
        page.vm.onPageChange({ page: 2, limit: 25 });
        await flushPromises();
        expect(page.repository.search).toHaveBeenLastCalledWith(
            expect.objectContaining({ page: 2, limit: 25 }),
            Shopware.Context.api,
        );
        expect(page.vm.isLoading).toBe(false);
        expect(notify).toHaveBeenCalled();
    });
});
