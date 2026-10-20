/**
 * @sw-package framework
 */
import { mount, RouterLinkStub, type VueWrapper } from '@vue/test-utils';
import type { ComponentPublicInstance } from 'vue';
import type CriteriaType from 'src/core/data/criteria.data';

type Webhook = {
    id: string;
    name: string;
    active: boolean;
    appId?: string;
    aclRoleIds?: string[];
    app?: { name: string; label: string };
    ownerUser?: { id: string; username: string };
    ownerIntegrationId?: string;
    ownerIntegration?: { label: string };
};

type ListPage = {
    createNotificationSuccess: jest.Mock;
    createNotificationError: jest.Mock;
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
    app: { name: 'MyApp', label: 'My app' },
};

function createCollection<T>(items: T[]) {
    return Object.assign([...items], { total: items.length });
}

function emitFromListing(wrapper: VueWrapper, event: string, ...args: unknown[]): void {
    (wrapper.findComponent({ name: 'sw-entity-listing' }).vm as ComponentPublicInstance).$emit(event, ...args);
}

function criteriaOf(search: jest.Mock): CriteriaType {
    return (search.mock.calls[0] as [CriteriaType])[0];
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
} = {}) {
    const webhookSearch = jest.fn(() => Promise.resolve(createCollection(webhooks.map((webhook) => ({ ...webhook })))));
    const roleSearch = jest.fn((criteria: CriteriaType) => {
        const ids = (criteria.filters[0] as { value: string }).value.split('|');

        return Promise.resolve(createCollection(ids.map((id) => ({ id, name: `Role ${id}` }))));
    });
    const appSearch = jest.fn(() =>
        Promise.resolve(createCollection([{ integrationId: 'app-integration', label: 'Other app' }])),
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
                searchRankingService: {},
            },
            mocks: {
                $route: { query: { page: 1, limit: 25, term } },
            },
            stubs: {
                'sw-page': {
                    template: '<div class="sw-page"><slot name="search-bar"></slot><slot name="content"></slot></div>',
                },
                'sw-search-bar': true,
                'sw-entity-listing': {
                    name: 'sw-entity-listing',
                    props: [
                        'dataSource',
                        'allowInlineEdit',
                        'allowDelete',
                    ],
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
                'mt-badge': {
                    template: '<span class="mt-badge"><slot></slot></span>',
                },
                'router-link': RouterLinkStub,
                'mt-empty-state': true,
            },
        },
    });

    await flushPromises();

    const page = wrapper.vm as unknown as ListPage;
    page.createNotificationSuccess = jest.fn();
    page.createNotificationError = jest.fn();

    return { wrapper, page, webhookSearch, roleSearch, appSearch };
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

    it('searches the webhooks by the term of the route', async () => {
        const { webhookSearch } = await createWrapper({ term: 'order' });

        expect(criteriaOf(webhookSearch).term).toBe('order');
    });

    it('shows the owner type and links to the owner', async () => {
        const { wrapper } = await createWrapper();

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
        expect(owner.findComponent(RouterLinkStub).text()).toBe('Other app');
    });

    it('lists the roles a webhook is limited to', async () => {
        const { wrapper, roleSearch } = await createWrapper();

        expect(roleSearch).toHaveBeenCalledTimes(1);
        // the browser collapses the whitespace between the separator and the link
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

    it('does not load roles or apps when no webhook needs them', async () => {
        const { roleSearch, appSearch } = await createWrapper({ webhooks: [appWebhook] });

        expect(roleSearch).not.toHaveBeenCalled();
        expect(appSearch).not.toHaveBeenCalled();
    });

    it('shows whether a webhook is active and switches it in inline edit', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.find('.row-user-webhook .active .is--active').exists()).toBe(true);
        expect(wrapper.find('.row-integration-webhook .active .is--inactive').exists()).toBe(true);
        expect(wrapper.find('.row-user-webhook .active-edit input[type="checkbox"]').exists()).toBe(true);
    });

    it('allows inline editing only with the editor role', async () => {
        const { wrapper: viewer } = await createWrapper();
        const { wrapper: editor } = await createWrapper({ privileges: ['webhook.editor'] });

        expect(viewer.findComponent({ name: 'sw-entity-listing' }).props('allowInlineEdit')).toBeUndefined();
        expect(editor.findComponent({ name: 'sw-entity-listing' }).props('allowInlineEdit')).toBe(true);
    });

    it('notifies when an inline edit was saved', async () => {
        const { wrapper, page, webhookSearch } = await createWrapper({ privileges: ['webhook.editor'] });

        emitFromListing(wrapper, 'inline-edit-save', Promise.resolve(), userWebhook);
        await flushPromises();

        expect(page.createNotificationSuccess).toHaveBeenCalledWith({
            message: 'sw-settings-webhook.list.messageSaveSuccess',
        });
        expect(webhookSearch).toHaveBeenCalledTimes(1);
    });

    it('reloads the list and shows an error when an inline edit fails', async () => {
        const { wrapper, page, webhookSearch } = await createWrapper({ privileges: ['webhook.editor'] });

        emitFromListing(wrapper, 'inline-edit-save', Promise.reject(new Error('refused')), userWebhook);
        await flushPromises();

        expect(page.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-settings-webhook.list.messageSaveError',
        });
        expect(webhookSearch).toHaveBeenCalledTimes(2);
    });

    it('opens and closes the deliveries of a webhook', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.find('.deliveries-modal').exists()).toBe(false);

        await wrapper.find('.row-user-webhook .more button').trigger('click');

        const modal = wrapper.findComponent({ name: 'sw-settings-webhook-deliveries-modal' });
        expect(modal.props('webhook')).toEqual(expect.objectContaining({ id: 'user-webhook' }));

        (modal.vm as ComponentPublicInstance).$emit('modal-close');
        await flushPromises();

        expect(wrapper.find('.deliveries-modal').exists()).toBe(false);
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

    it('shows an error when deleting fails', async () => {
        const { wrapper, page } = await createWrapper({ privileges: ['webhook.deleter'] });

        emitFromListing(wrapper, 'delete-item-failed');

        expect(page.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-settings-webhook.list.messageDeleteError',
        });
    });

    it('shows the empty state when there are no webhooks', async () => {
        const { wrapper } = await createWrapper({ webhooks: [] });

        expect(wrapper.find('mt-empty-state-stub').exists()).toBe(true);
        expect(wrapper.find('.sw-entity-listing').exists()).toBe(false);
    });
});
