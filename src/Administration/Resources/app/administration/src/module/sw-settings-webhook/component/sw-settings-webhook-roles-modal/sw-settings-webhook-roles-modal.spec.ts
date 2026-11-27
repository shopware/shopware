/**
 * @sw-package framework
 */
import { mount, type VueWrapper } from '@vue/test-utils';
import type { ComponentPublicInstance } from 'vue';
import type CriteriaType from 'src/core/data/criteria.data';

function child(wrapper: VueWrapper, name: string): VueWrapper<ComponentPublicInstance> {
    return wrapper.findComponent({ name }) as VueWrapper<ComponentPublicInstance>;
}

type Owner = { admin: boolean; aclRoles: { id: string }[] };

type Webhook = {
    id: string;
    name: string;
    aclRoleIds: string[] | null;
    ownerUser?: Owner;
    ownerIntegration?: Owner;
};

const webhook = { id: 'webhook-1', name: 'Order webhook' };

const roleNames: Record<string, string> = { 'role-1': 'Editor', 'role-2': 'Viewer', 'role-3': 'Former role' };

function roles(ids: string[]) {
    return new Shopware.Data.EntityCollection(
        '/acl-role',
        'acl_role',
        Shopware.Context.api,
        null,
        ids.map((id) => ({ id, name: roleNames[id] }) as Entity<'acl_role'>),
    );
}

function select(wrapper: VueWrapper) {
    return wrapper.findComponent({ name: 'sw-entity-multi-select' });
}

function selectedIds(wrapper: VueWrapper): string[] {
    return Array.from(select(wrapper).props('entityCollection') as EntityCollection<'acl_role'>, (role) => role.id);
}

async function createWrapper({
    loaded = {
        ...webhook,
        aclRoleIds: ['role-1'],
        ownerUser: { admin: false, aclRoles: [{ id: 'role-1' }, { id: 'role-2' }] },
    } as Webhook,
    save = jest.fn(() => Promise.resolve()),
} = {}) {
    const get = jest.fn(() => Promise.resolve({ ...loaded }));
    const search = jest.fn((criteria: CriteriaType) => Promise.resolve(roles(criteria.ids)));

    const wrapper = mount(await wrapTestComponent('sw-settings-webhook-roles-modal', { sync: true }), {
        props: { webhook },
        global: {
            provide: {
                repositoryFactory: {
                    create: () => ({ get, save, search, route: '/acl-role' }),
                },
            },
            stubs: {
                'mt-modal-root': {
                    template: '<div class="mt-modal-root"><slot></slot></div>',
                },
                'mt-modal': {
                    template: `
                        <div class="mt-modal">
                            <slot name="default"></slot>
                            <div class="mt-modal__footer"><slot name="footer"></slot></div>
                        </div>`,
                },
                'sw-entity-multi-select': {
                    name: 'sw-entity-multi-select',
                    props: ['criteria', 'entityCollection', 'disabled'],
                    emits: ['update:entityCollection'],
                    template: '<div class="sw-entity-multi-select"></div>',
                },
                'mt-banner': {
                    template: '<div class="mt-banner"><slot></slot></div>',
                },
                'mt-button': {
                    emits: ['click'],
                    template: '<button @click="$emit(\'click\')"><slot></slot></button>',
                },
                'sw-loader': true,
            },
        },
    });

    await flushPromises();

    const createNotification = jest.spyOn(Shopware.Store.get('notification'), 'createNotification');

    return { wrapper, get, save, search, createNotification };
}

describe('src/module/sw-settings-webhook/component/sw-settings-webhook-roles-modal', () => {
    it('offers only the roles of the owner that are not deleted and not of an app', async () => {
        const { wrapper } = await createWrapper();

        expect((select(wrapper).props('criteria') as CriteriaType).filters).toEqual([
            expect.objectContaining({ type: 'equals', field: 'deletedAt', value: null }),
            expect.objectContaining({ type: 'equals', field: 'app.id', value: null }),
            expect.objectContaining({ type: 'equalsAny', field: 'id', value: 'role-1|role-2' }),
        ]);
        expect(selectedIds(wrapper)).toEqual(['role-1']);
    });

    it('offers all roles that are not deleted and not of an app when the owner is an admin', async () => {
        const { wrapper } = await createWrapper({
            loaded: { ...webhook, aclRoleIds: null, ownerIntegration: { admin: true, aclRoles: [] } },
        });

        expect((select(wrapper).props('criteria') as CriteriaType).filters).toEqual([
            expect.objectContaining({ type: 'equals', field: 'deletedAt', value: null }),
            expect.objectContaining({ type: 'equals', field: 'app.id', value: null }),
        ]);
    });

    it('saves the picked roles, then reports the save and closes', async () => {
        const { wrapper, save, createNotification } = await createWrapper();

        child(wrapper, 'sw-entity-multi-select').vm.$emit('update:entityCollection', roles(['role-1', 'role-2']));
        await wrapper.find('.sw-settings-webhook-roles-modal__save').trigger('click');
        await flushPromises();

        expect(save).toHaveBeenCalledWith(expect.objectContaining({ aclRoleIds: ['role-1', 'role-2'] }));
        expect(createNotification).toHaveBeenCalledWith(
            expect.objectContaining({ variant: 'success', message: 'sw-settings-webhook.list.messageSaveSuccess' }),
        );
        expect(wrapper.emitted('saved')).toHaveLength(1);
        expect(wrapper.emitted('modal-close')).toHaveLength(1);
    });

    it('saves an empty selection as all roles of the owner', async () => {
        const { wrapper, save } = await createWrapper();

        child(wrapper, 'sw-entity-multi-select').vm.$emit('update:entityCollection', roles([]));
        await wrapper.find('.sw-settings-webhook-roles-modal__save').trigger('click');
        await flushPromises();

        expect(save).toHaveBeenCalledWith(expect.objectContaining({ aclRoleIds: undefined }));
    });

    it('explains when the roles do not allow the event of the webhook', async () => {
        const { wrapper, createNotification } = await createWrapper({
            save: jest.fn(() =>
                Promise.reject(
                    Object.assign(new Error('refused'), {
                        response: { data: { errors: [{ code: 'FRAMEWORK__WEBHOOK_EVENT_PRIVILEGES_MISSING' }] } },
                    }),
                ),
            ),
        });

        await wrapper.find('.sw-settings-webhook-roles-modal__save').trigger('click');
        await flushPromises();

        expect(createNotification).toHaveBeenCalledWith(
            expect.objectContaining({ variant: 'error', message: 'sw-settings-webhook.roles.messageEventNotCovered' }),
        );
        expect(wrapper.emitted('modal-close')).toBeUndefined();
    });

    it('keeps a role the owner no longer has selected and names it', async () => {
        const { wrapper, search } = await createWrapper({
            loaded: {
                ...webhook,
                aclRoleIds: ['role-3'],
                ownerUser: { admin: false, aclRoles: [{ id: 'role-1' }] },
            },
        });

        expect(search.mock.calls[0][0].filters).toEqual([]);
        expect(selectedIds(wrapper)).toEqual(['role-3']);
        expect(wrapper.find('.sw-settings-webhook-roles-modal__unheld-roles').exists()).toBe(true);
    });

    it('saves the roles unchanged when the selection is not changed', async () => {
        const { wrapper, save } = await createWrapper({
            loaded: {
                ...webhook,
                aclRoleIds: ['role-3'],
                ownerUser: { admin: false, aclRoles: [{ id: 'role-1' }] },
            },
        });

        await wrapper.find('.sw-settings-webhook-roles-modal__save').trigger('click');
        await flushPromises();

        expect(save).toHaveBeenCalledWith(expect.objectContaining({ aclRoleIds: ['role-3'] }));
    });
});
