/**
 * @sw-package framework
 */
import { mount, RouterLinkStub, type VueWrapper } from '@vue/test-utils';
import type { ComponentPublicInstance } from 'vue';
import type CriteriaType from 'src/core/data/criteria.data';

const { Criteria } = Shopware.Data;

type Webhook = {
    id: string;
    name: string;
    active: boolean;
    appId?: string;
    aclRoleIds?: string[];
    app?: { name: string; label: string | null; translated: { label: string }; active: boolean };
    ownerUser?: { id: string; username: string };
    ownerIntegrationId?: string;
    ownerIntegration?: { label: string };
};

const userWebhook: Webhook = {
    id: 'user-webhook',
    name: 'User webhook',
    active: true,
    aclRoleIds: [
        'role-1',
        'role-2',
    ],
    ownerUser: { id: 'user-id', username: 'admin' },
};

const integrationWebhook: Webhook = {
    id: 'integration-webhook',
    name: 'Integration webhook',
    active: false,
    ownerIntegrationId: 'erp-integration',
    ownerIntegration: { label: 'ERP' },
};

const appIntegrationWebhook: Webhook = {
    id: 'app-integration-webhook',
    name: 'App integration webhook',
    active: true,
    ownerIntegrationId: 'app-integration',
    ownerIntegration: { label: 'App integration' },
};

const appWebhook: Webhook = {
    id: 'app-webhook',
    name: 'App webhook',
    active: true,
    appId: 'app-id',
    app: { name: 'MyApp', label: null, translated: { label: 'My app' }, active: true },
};

const inactiveAppWebhook: Webhook = {
    id: 'inactive-app-webhook',
    name: 'Inactive app webhook',
    active: true,
    appId: 'inactive-app-id',
    app: { name: 'InactiveApp', label: null, translated: { label: 'Inactive app' }, active: false },
};

const deletedRoleWebhook: Webhook = {
    id: 'deleted-role-webhook',
    name: 'Deleted role webhook',
    active: true,
    aclRoleIds: ['deleted-role'],
    ownerUser: { id: 'user-id', username: 'admin' },
};

const existingRoles = [
    { id: 'role-1', name: 'Role role-1' },
    { id: 'role-2', name: 'Role role-2' },
];

function createCollection<T>(items: T[]) {
    return Object.assign([...items], { total: items.length });
}

function child(wrapper: VueWrapper, name: string): VueWrapper<ComponentPublicInstance> {
    return wrapper.findComponent({ name }) as VueWrapper<ComponentPublicInstance>;
}

function emitFromListing(wrapper: VueWrapper, event: string, ...args: unknown[]): void {
    child(wrapper, 'sw-entity-listing').vm.$emit(event, ...args);
}

function criteriaOf(search: jest.Mock): CriteriaType {
    return (search.mock.lastCall as [CriteriaType])[0];
}

async function selectOwners(wrapper: VueWrapper, owners: string[]): Promise<void> {
    child(wrapper, 'mt-select').vm.$emit('update:model-value', owners);
    await flushPromises();
}

async function createWrapper({
    privileges = [] as string[],
    webhooks = [
        userWebhook,
        integrationWebhook,
        appIntegrationWebhook,
        appWebhook,
    ] as Webhook[],
    term = undefined as string | undefined,
    page = 1,
    failingSearch = undefined as jest.Mock | undefined,
} = {}) {
    const webhookSearch =
        failingSearch ?? jest.fn(() => Promise.resolve(createCollection(webhooks.map((webhook) => ({ ...webhook })))));
    const roleSearch = jest.fn((criteria: CriteriaType) => {
        const ids = (criteria.filters[0] as { value: string }).value.split('|');

        return Promise.resolve(createCollection(existingRoles.filter((role) => ids.includes(role.id))));
    });
    const appSearch = jest.fn(() =>
        Promise.resolve(
            createCollection([{ integrationId: 'app-integration', label: null, translated: { label: 'Other app' } }]),
        ),
    );

    const wrapper = mount(await wrapTestComponent('sw-settings-webhook-list', { sync: true }), {
        global: {
            provide: {
                repositoryFactory: {
                    create: (entity: string) => {
                        if (entity === 'acl_role') {
                            return { search: roleSearch };
                        }

                        if (entity === 'app') {
                            return { search: appSearch };
                        }

                        return { search: webhookSearch };
                    },
                },
                acl: {
                    can: (privilege: string) => privileges.includes(privilege),
                },
                searchRankingService: {
                    isValidTerm: (searchTerm?: string) => (searchTerm ?? '').trim().length > 0,
                },
            },
            mocks: {
                $route: { query: { page, limit: 25, term } },
            },
            stubs: {
                'sw-page': {
                    template:
                        '<div class="sw-page"><slot name="search-bar"></slot><slot name="content"></slot><slot name="sidebar"></slot></div>',
                },
                'sw-search-bar': true,
                'sw-sidebar': {
                    template: '<div class="sw-sidebar"><slot></slot></div>',
                },
                'sw-sidebar-item': {
                    template: '<div class="sw-sidebar-item"><slot></slot></div>',
                },
                'sw-sidebar-collapse': {
                    template:
                        '<div class="sw-sidebar-collapse"><slot name="header"></slot><slot name="content"></slot></div>',
                },
                'mt-select': {
                    name: 'mt-select',
                    props: ['modelValue'],
                    template: '<div class="mt-select"></div>',
                },
                'sw-entity-listing': {
                    name: 'sw-entity-listing',
                    props: {
                        dataSource: { type: Array, required: true },
                        allowInlineEdit: { type: Boolean, default: true },
                        allowDelete: { type: Boolean, default: true },
                    },
                    template: `
                        <div class="sw-entity-listing">
                            <div v-for="item in dataSource" :class="'row-' + item.id">
                                <div class="owner"><slot name="column-owner" v-bind="{ item }"></slot></div>
                                <div class="roles"><slot name="column-roles" v-bind="{ item }"></slot></div>
                                <div class="active"><slot name="column-active" v-bind="{ item, isInlineEdit: false }"></slot></div>
                                <div class="active-edit"><slot name="column-active" v-bind="{ item, isInlineEdit: true }"></slot></div>
                                <div class="more"><slot name="more-actions" v-bind="{ item }"></slot></div>
                                <div class="delete">
                                    <slot name="delete-action" v-bind="{ item, allowDelete, showDelete: () => {} }"></slot>
                                </div>
                            </div>
                        </div>`,
                },
                'sw-context-menu-item': {
                    props: ['disabled'],
                    emits: ['click'],
                    template:
                        '<button class="sw-context-menu-item" :disabled="disabled" @click="$emit(\'click\')"><slot></slot></button>',
                },
                'sw-settings-webhook-deliveries-modal': {
                    name: 'sw-settings-webhook-deliveries-modal',
                    props: ['webhook'],
                    template: '<div class="deliveries-modal"></div>',
                },
                'sw-settings-webhook-secret-modal': {
                    name: 'sw-settings-webhook-secret-modal',
                    props: ['webhook'],
                    template: '<div class="secret-modal"></div>',
                },
                'mt-badge': {
                    template: '<span class="mt-badge"><slot></slot></span>',
                },
                'router-link': RouterLinkStub,
                'mt-empty-state': true,
            },
        },
    });

    await flushPromises();

    const createNotification = jest.spyOn(Shopware.Store.get('notification'), 'createNotification');

    return { wrapper, createNotification, webhookSearch, roleSearch, appSearch };
}

describe('src/module/sw-settings-webhook/page/sw-settings-webhook-list', () => {
    it('loads the webhooks with their app and owner, sorted by name', async () => {
        const { webhookSearch } = await createWrapper();

        expect(webhookSearch).toHaveBeenCalledTimes(1);
        const criteria = criteriaOf(webhookSearch);
        expect(criteria.hasAssociation('app')).toBe(true);
        expect(criteria.hasAssociation('ownerUser')).toBe(true);
        expect(criteria.hasAssociation('ownerIntegration')).toBe(true);
        expect(criteria.sortings).toEqual([expect.objectContaining({ field: 'name', order: 'ASC' })]);
    });

    it('shows only the webhooks of users and integrations by default', async () => {
        const { wrapper, webhookSearch } = await createWrapper();

        expect(wrapper.findComponent({ name: 'mt-select' }).props('modelValue')).toEqual(['user']);
        expect(criteriaOf(webhookSearch).filters).toEqual([
            Criteria.multi('OR', [
                Criteria.multi('AND', [
                    Criteria.equals('appId', null),
                    Criteria.equals('ownerIntegration.app.id', null),
                ]),
            ]),
        ]);
    });

    it('shows the webhooks of the selected apps and services, from the first page', async () => {
        const { wrapper, webhookSearch } = await createWrapper({ page: 2 });

        await selectOwners(wrapper, ['app', 'service']);

        expect(criteriaOf(webhookSearch).page).toBe(1);
        expect(criteriaOf(webhookSearch).filters).toEqual([
            Criteria.multi('OR', [
                Criteria.multi('OR', [
                    Criteria.equals('app.selfManaged', false),
                    Criteria.equals('ownerIntegration.app.selfManaged', false),
                ]),
                Criteria.multi('OR', [
                    Criteria.equals('app.selfManaged', true),
                    Criteria.equals('ownerIntegration.app.selfManaged', true),
                ]),
            ]),
        ]);
    });

    it('shows all webhooks when no owner is selected', async () => {
        const { wrapper, webhookSearch } = await createWrapper();

        await selectOwners(wrapper, []);

        expect(criteriaOf(webhookSearch).filters).toEqual([]);
    });

    it('searches the webhooks by the term of the route', async () => {
        const { webhookSearch } = await createWrapper({ term: 'order' });

        expect(criteriaOf(webhookSearch).term).toBe('order');
    });

    it('shows the owner type and links to the owner', async () => {
        const { wrapper } = await createWrapper({
            privileges: [
                'users_and_permissions.viewer',
                'integration.viewer',
                'system.plugin_maintain',
            ],
        });

        const owner = (id: string) => ({
            type: wrapper.find(`.row-${id} .owner .mt-badge`).text(),
            link: wrapper.find(`.row-${id} .owner`).findComponent(RouterLinkStub),
        });

        expect(owner('user-webhook').type).toBe('sw-settings-webhook.list.ownerUser');
        expect(owner('user-webhook').link.text()).toBe('admin');
        expect(owner('user-webhook').link.props('to')).toEqual({
            name: 'sw.users.permissions.user.detail',
            params: { id: 'user-id' },
        });

        expect(owner('integration-webhook').type).toBe('sw-settings-webhook.list.ownerIntegration');
        expect(owner('integration-webhook').link.text()).toBe('ERP');
        expect(owner('integration-webhook').link.props('to')).toEqual({ name: 'sw.integration.index' });

        expect(owner('app-webhook').type).toBe('sw-settings-webhook.list.ownerApp');
        expect(owner('app-webhook').link.text()).toBe('My app');
        expect(owner('app-webhook').link.props('to')).toEqual({ name: 'sw.extension.my-extensions.listing.app' });
    });

    it('shows the app of a webhook its app integration created', async () => {
        const { wrapper, appSearch } = await createWrapper();

        expect(appSearch).toHaveBeenCalledTimes(1);
        expect(criteriaOf(appSearch).filters).toEqual([
            expect.objectContaining({ field: 'integrationId', value: 'erp-integration|app-integration' }),
        ]);

        const owner = wrapper.find('.row-app-integration-webhook .owner');
        expect(owner.find('.mt-badge').text()).toBe('sw-settings-webhook.list.ownerAppIntegration');
        expect(owner.text()).toContain('Other app');
    });

    it('shows owners and roles without links when the user may not open them', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.find('.row-user-webhook .owner').text()).toContain('admin');
        expect(wrapper.find('.row-integration-webhook .owner').text()).toContain('ERP');
        expect(wrapper.find('.row-app-webhook .owner').text()).toContain('My app');
        expect(wrapper.find('.row-user-webhook .roles').text().replace(/\s+/g, ' ')).toBe('Role role-1, Role role-2');
        expect(wrapper.findAllComponents(RouterLinkStub)).toHaveLength(0);
    });

    it('lists the roles a webhook is limited to', async () => {
        const { wrapper, roleSearch } = await createWrapper({ privileges: ['users_and_permissions.viewer'] });

        expect(roleSearch).toHaveBeenCalledTimes(1);
        expect(wrapper.find('.row-user-webhook .roles').text().replace(/\s+/g, ' ')).toBe('Role role-1, Role role-2');
        const roleLinks = wrapper
            .findAllComponents(RouterLinkStub)
            .filter((link) => !!(link.element as Element).closest('.row-user-webhook .roles'));
        expect(roleLinks.map((link) => link.props('to') as unknown)).toEqual([
            { name: 'sw.users.permissions.role.detail', params: { id: 'role-1' } },
            { name: 'sw.users.permissions.role.detail', params: { id: 'role-2' } },
        ]);
        expect(wrapper.find('.row-integration-webhook .roles').text()).toBe('');
    });

    it('shows a deleted role without a link and does not look it up again', async () => {
        const { wrapper, roleSearch } = await createWrapper({
            privileges: ['users_and_permissions.viewer'],
            webhooks: [deletedRoleWebhook],
        });

        expect(wrapper.find('.row-deleted-role-webhook .roles').text()).toBe('sw-settings-webhook.list.deletedRole');
        expect(wrapper.find('.row-deleted-role-webhook .roles').findComponent(RouterLinkStub).exists()).toBe(false);

        emitFromListing(wrapper, 'update-records', createCollection([deletedRoleWebhook]));
        await flushPromises();

        expect(roleSearch).toHaveBeenCalledTimes(1);
    });

    it('does not load roles or apps when no webhook needs them', async () => {
        const { roleSearch, appSearch } = await createWrapper({ webhooks: [appWebhook] });

        expect(roleSearch).not.toHaveBeenCalled();
        expect(appSearch).not.toHaveBeenCalled();
    });

    it('shows whether a webhook is active and switches it in inline edit', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.find('.row-user-webhook .active .is--active').exists()).toBe(true);
        expect(wrapper.find('.row-integration-webhook .active .is--inactive').exists()).toBe(true);

        await wrapper.find('.row-user-webhook .active-edit input[type="checkbox"]').setValue(false);

        expect(wrapper.find('.row-user-webhook .active .is--inactive').exists()).toBe(true);
    });

    it('shows a webhook of an inactive app as inactive', async () => {
        const { wrapper } = await createWrapper({ webhooks: [inactiveAppWebhook] });

        expect(wrapper.find('.row-inactive-app-webhook .active .is--inactive').exists()).toBe(true);
        expect(wrapper.find('.row-inactive-app-webhook .active .is--active').exists()).toBe(false);
    });

    it('allows inline editing only with the editor role', async () => {
        const { wrapper: viewer } = await createWrapper();
        const { wrapper: editor } = await createWrapper({ privileges: ['webhook.editor'] });

        expect(viewer.findComponent({ name: 'sw-entity-listing' }).props('allowInlineEdit')).toBe(false);
        expect(editor.findComponent({ name: 'sw-entity-listing' }).props('allowInlineEdit')).toBe(true);
    });

    it('notifies when an inline edit was saved', async () => {
        const { wrapper, createNotification, webhookSearch } = await createWrapper();

        emitFromListing(wrapper, 'inline-edit-save', Promise.resolve(), userWebhook);
        await flushPromises();

        expect(createNotification).toHaveBeenCalledWith(
            expect.objectContaining({ variant: 'success', message: 'sw-settings-webhook.list.messageSaveSuccess' }),
        );
        expect(webhookSearch).toHaveBeenCalledTimes(1);
    });

    it('reloads the list and shows an error when an inline edit fails', async () => {
        const { wrapper, createNotification, webhookSearch } = await createWrapper();

        emitFromListing(wrapper, 'inline-edit-save', Promise.reject(new Error('refused')), userWebhook);
        await flushPromises();

        expect(createNotification).toHaveBeenCalledWith(
            expect.objectContaining({ variant: 'error', message: 'sw-settings-webhook.list.messageSaveError' }),
        );
        expect(webhookSearch).toHaveBeenCalledTimes(2);
    });

    it('opens and closes the deliveries of a webhook', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.find('.deliveries-modal').exists()).toBe(false);

        await wrapper.find('.row-user-webhook .more button').trigger('click');

        expect(wrapper.findComponent({ name: 'sw-settings-webhook-deliveries-modal' }).props('webhook')).toEqual(
            expect.objectContaining({ id: 'user-webhook' }),
        );

        child(wrapper, 'sw-settings-webhook-deliveries-modal').vm.$emit('modal-close');
        await flushPromises();

        expect(wrapper.find('.deliveries-modal').exists()).toBe(false);
    });

    it('offers a signing secret only for webhooks without an app', async () => {
        const { wrapper } = await createWrapper({ privileges: ['webhook.editor'] });

        expect(wrapper.find('.row-user-webhook .sw-settings-webhook-list__secret-action').exists()).toBe(true);
        expect(wrapper.find('.row-integration-webhook .sw-settings-webhook-list__secret-action').exists()).toBe(true);
        expect(wrapper.find('.row-app-integration-webhook .sw-settings-webhook-list__secret-action').exists()).toBe(false);
        expect(wrapper.find('.row-app-webhook .sw-settings-webhook-list__secret-action').exists()).toBe(false);
    });

    it('does not allow setting a signing secret without the editor role', async () => {
        const { wrapper } = await createWrapper();

        expect(
            wrapper.find('.row-user-webhook .sw-settings-webhook-list__secret-action').attributes('disabled'),
        ).toBeDefined();
    });

    it('opens and closes the signing secret of a webhook', async () => {
        const { wrapper } = await createWrapper({ privileges: ['webhook.editor'] });

        await wrapper.find('.row-user-webhook .sw-settings-webhook-list__secret-action').trigger('click');

        expect(wrapper.findComponent({ name: 'sw-settings-webhook-secret-modal' }).props('webhook')).toEqual(
            expect.objectContaining({ id: 'user-webhook' }),
        );

        child(wrapper, 'sw-settings-webhook-secret-modal').vm.$emit('modal-close');
        await flushPromises();

        expect(wrapper.find('.secret-modal').exists()).toBe(false);
    });

    it('allows deleting webhooks, except those of apps', async () => {
        const { wrapper } = await createWrapper({ privileges: ['webhook.deleter'] });

        expect(wrapper.find('.row-user-webhook .delete button').attributes('disabled')).toBeUndefined();
        expect(wrapper.find('.row-integration-webhook .delete button').attributes('disabled')).toBeUndefined();
        expect(wrapper.find('.row-app-webhook .delete button').attributes('disabled')).toBeDefined();
    });

    it('does not allow deleting without the deleter role', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.find('.row-user-webhook .delete button').attributes('disabled')).toBeDefined();
    });

    it('takes the webhooks the listing searched after a delete', async () => {
        const { wrapper, roleSearch } = await createWrapper({ webhooks: [appWebhook] });

        emitFromListing(wrapper, 'update-records', createCollection([userWebhook]));
        await flushPromises();

        expect(wrapper.find('.row-app-webhook').exists()).toBe(false);
        expect(wrapper.find('.row-user-webhook').exists()).toBe(true);
        expect(roleSearch).toHaveBeenCalledTimes(1);

        emitFromListing(wrapper, 'update-records', createCollection([]));
        await flushPromises();

        expect(wrapper.find('mt-empty-state-stub').exists()).toBe(true);
    });

    it('shows an error when deleting fails', async () => {
        const { wrapper, createNotification } = await createWrapper();

        emitFromListing(wrapper, 'delete-item-failed');

        expect(createNotification).toHaveBeenCalledWith(
            expect.objectContaining({ variant: 'error', message: 'sw-settings-webhook.list.messageDeleteError' }),
        );
    });

    it('shows the empty state when there are no webhooks', async () => {
        const { wrapper } = await createWrapper({ webhooks: [] });

        expect(wrapper.find('mt-empty-state-stub').exists()).toBe(true);
        expect(wrapper.find('.sw-entity-listing').exists()).toBe(false);
    });

    it('says that no webhook was found when the selected owners have none', async () => {
        const { wrapper } = await createWrapper({ webhooks: [] });

        expect(wrapper.find('mt-empty-state-stub').attributes('headline')).toBe('sw-empty-state.messageNoResultTitle');

        await selectOwners(wrapper, []);

        expect(wrapper.find('mt-empty-state-stub').attributes('headline')).toBe(
            'sw-settings-webhook.list.emptyStateHeadline',
        );
    });

    it('says that no webhook was found when a search has no hits', async () => {
        const { wrapper } = await createWrapper({ webhooks: [], term: 'order' });

        await selectOwners(wrapper, []);

        expect(wrapper.find('mt-empty-state-stub').attributes('headline')).toBe('sw-empty-state.messageNoResultTitle');
    });

    it('shows an error instead of a blank page when the webhooks cannot be loaded', async () => {
        const { wrapper } = await createWrapper({
            failingSearch: jest.fn(() => Promise.reject(new Error('Bad Request'))),
        });

        expect(wrapper.find('.sw-settings-webhook-list__load-error').exists()).toBe(true);
        expect(wrapper.find('.sw-entity-listing').exists()).toBe(false);
    });
});
