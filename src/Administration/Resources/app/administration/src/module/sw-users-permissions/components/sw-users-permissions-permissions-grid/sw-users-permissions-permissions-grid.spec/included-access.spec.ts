/**
 * @sw-package fundamentals@framework
 */
import { reactive } from 'vue';
import { mount } from '@vue/test-utils';
import PrivilegesService from 'src/app/service/privileges.service';

type Mapping = {
    category: 'permissions';
    parent: string;
    key: string;
    roles: Record<string, { privileges: string[]; dependencies: string[] }>;
    containsPersonalData?: boolean | string[];
};

function permission(
    parent: string,
    key: string,
    roles: Record<string, string[]>,
    containsPersonalData?: boolean | string[],
): Mapping {
    return {
        category: 'permissions',
        parent,
        key,
        roles: Object.fromEntries(
            Object.entries(roles).map(([role, privileges]) => [
                role,
                { privileges, dependencies: [] },
            ]),
        ),
        ...(containsPersonalData === undefined ? {} : { containsPersonalData }),
    };
}

const mappings: Mapping[] = [
    permission(
        'customers',
        'customer',
        {
            viewer: ['customer:read'],
            editor: ['customer:update'],
            creator: ['customer:create'],
            deleter: ['customer:delete'],
        },
        true,
    ),
    permission('orders', 'order', { viewer: ['order:read'], creator: ['order:create'] }, true),
    permission('settings', 'country', {
        viewer: [
            'country:read',
            'customer:read',
        ],
        creator: ['country:create'],
    }),
    permission('settings', 'tag', {
        viewer: [
            'tag:read',
            'customer:read',
            'order:read',
        ],
        creator: ['tag:create'],
    }),
    permission('marketing', 'promotion', {
        viewer: [
            'promotion:read',
            'customer:read',
        ],
        editor: [
            'promotion:update',
            'rule:read',
            'rule:update',
        ],
        creator: ['promotion:create'],
    }),
    permission('settings', 'rule', { viewer: ['rule:read'], creator: ['rule:create'] }),
    permission('catalogues', 'product', {
        viewer: [
            'product:read',
            'media:read',
        ],
        creator: ['product:create'],
    }),
    permission('content', 'media', { viewer: ['media:read'], creator: ['media:create'] }),
];

const IncludedAccessModalStub = {
    name: 'IncludedAccessModalStub',
    props: [
        'privilegeKeys',
        'title',
        'subtitle',
        'description',
        'withSources',
    ],
    emits: ['close'],
    template: `<div>
        <button class="included-access-modal-stub__close" @click="$emit('close')" />
    </div>`,
};

const translate = (key: string, params: Record<string, unknown> = {}) => {
    return Object.entries(params).reduce((text, [name, value]) => `${text} ${name}=${String(value)}`, key);
};

async function createWrapper(rolePrivileges: string[] = []) {
    const privilegesService = new PrivilegesService();
    mappings.forEach((mapping) => privilegesService.addPrivilegeMappingEntry(mapping));

    const wrapper = mount(await wrapTestComponent('sw-users-permissions-permissions-grid', { sync: true }), {
        global: {
            renderStubDefaultSlot: true,
            stubs: {
                'sw-users-permissions-included-access-modal': IncludedAccessModalStub,
            },
            provide: { privileges: privilegesService },
            mocks: {
                $t: translate,
                $tc: translate,
            },
        },
        props: reactive({ role: { privileges: rolePrivileges } }),
    });

    await flushPromises();

    return { wrapper, privilegesService };
}

const row = (key: string) => `.sw-users-permissions-permissions-grid__entry_${key}`;
const rowButton = (key: string) => `${row(key)} .sw-users-permissions-permissions-grid__included-access-row-button`;
const roleButton = '.sw-users-permissions-permissions-grid__included-access-button';
const checkbox = (key: string, role: string) => {
    return `${row(key)} .sw-users-permissions-permissions-grid__role_${role} .mt-field--checkbox__container input`;
};
const modal = (wrapper: ReturnType<typeof mount>) => wrapper.findComponent(IncludedAccessModalStub);

describe('src/module/sw-users-permissions/components/sw-users-permissions-permissions-grid included access', () => {
    it.each([
        ['country', '+1'],
        ['tag', '+2'],
        ['promotion', '+1'],
        ['product', '+1'],
    ])('should show the number of included areas on the %s row', async (key, label) => {
        const { wrapper } = await createWrapper();

        expect(wrapper.find(rowButton(key)).text()).toBe(label);
    });

    it.each([
        'customer',
        'media',
    ])('should not show a button on the %s row without included access', async (key) => {
        const { wrapper } = await createWrapper();

        expect(wrapper.find(rowButton(key)).exists()).toBe(false);
    });

    it('should mute the button and preview the lowest role while the row is not selected', async () => {
        const { wrapper } = await createWrapper();
        const button = wrapper.find(rowButton('tag'));

        expect(button.classes()).toContain('is--muted');
        expect(button.attributes('tooltip-mock-message')).toBe(
            'sw-users-permissions.roles.grid.includedAccessPreviewTooltip count=2 role=sw-privileges.roles.viewer',
        );
    });

    it('should count the selected roles once the row is selected', async () => {
        const { wrapper } = await createWrapper(['promotion.editor']);
        const button = wrapper.find(rowButton('promotion'));

        expect(button.classes()).not.toContain('is--muted');
        expect(button.attributes('tooltip-mock-message')).toBe(
            'sw-users-permissions.roles.grid.includedAccessTooltip count=1',
        );
    });

    it('should open the modal for a row that is not selected', async () => {
        const { wrapper } = await createWrapper();

        expect(modal(wrapper).exists()).toBe(false);

        await wrapper.find(rowButton('tag')).trigger('click');

        expect(modal(wrapper).props()).toEqual({
            privilegeKeys: ['tag.viewer'],
            title: 'sw-users-permissions.roles.grid.rowModalTitle area=sw-privileges.permissions.tag.label',
            subtitle: 'sw-users-permissions.roles.grid.rowModalSubtitlePreview role=sw-privileges.roles.viewer',
            description: 'sw-users-permissions.roles.grid.rowModalDescription area=sw-privileges.permissions.tag.label',
            withSources: false,
        });
    });

    it('should open the modal with the selected roles of a row', async () => {
        const { wrapper } = await createWrapper([
            'promotion.viewer',
            'promotion.editor',
        ]);

        await wrapper.find(rowButton('promotion')).trigger('click');

        expect(modal(wrapper).props('privilegeKeys')).toEqual([
            'promotion.viewer',
            'promotion.editor',
        ]);
        expect(modal(wrapper).props('subtitle')).toBe(
            'sw-users-permissions.roles.grid.rowModalSubtitleSelected roles=sw-privileges.roles.viewer, sw-privileges.roles.editor',
        );
    });

    it('should keep the role button in place but disabled while nothing is included', async () => {
        const { wrapper } = await createWrapper(['customer.viewer']);

        expect(wrapper.find(roleButton).text()).toBe('sw-users-permissions.roles.grid.reviewIncludedAccess count=0');
        expect(wrapper.find(roleButton).attributes('disabled')).toBeDefined();
    });

    it('should leave out on the role button what the owning area already grants', async () => {
        const { wrapper } = await createWrapper(['tag.viewer']);

        expect(wrapper.find(roleButton).text()).toBe('sw-users-permissions.roles.grid.reviewIncludedAccess count=2');

        await wrapper.find<HTMLInputElement>(checkbox('customer', 'viewer')).setValue(true);

        expect(wrapper.find(roleButton).text()).toBe('sw-users-permissions.roles.grid.reviewIncludedAccess count=1');

        await wrapper.find<HTMLInputElement>(checkbox('order', 'viewer')).setValue(true);

        expect(wrapper.find(roleButton).attributes('disabled')).toBeDefined();
    });

    it('should open the role modal with sources', async () => {
        const { wrapper } = await createWrapper([
            'tag.viewer',
            'product.viewer',
        ]);

        await wrapper.find(roleButton).trigger('click');

        expect(modal(wrapper).props()).toEqual({
            privilegeKeys: [
                'tag.viewer',
                'product.viewer',
            ],
            title: 'sw-users-permissions.roles.grid.roleModalTitle',
            subtitle: 'sw-users-permissions.roles.grid.roleModalSubtitle count=2',
            description: 'sw-users-permissions.roles.grid.roleModalDescription',
            withSources: true,
        });
    });

    it('should close the modal', async () => {
        const { wrapper } = await createWrapper();

        await wrapper.find(rowButton('tag')).trigger('click');
        await wrapper.find('.included-access-modal-stub__close').trigger('click');

        expect(modal(wrapper).exists()).toBe(false);
    });

    it('should pick up a plugin mapping registered at runtime', async () => {
        const { wrapper, privilegesService } = await createWrapper();

        privilegesService.addPrivilegeMappingEntry(
            permission('customers', 'swag_loyalty', {
                viewer: [
                    'swag_loyalty_points:read',
                    'order:read',
                ],
                creator: ['swag_loyalty_points:create'],
            }),
        );
        await flushPromises();

        expect(wrapper.find(rowButton('swag_loyalty')).text()).toBe('+1');

        await wrapper.find<HTMLInputElement>(checkbox('swag_loyalty', 'viewer')).setValue(true);

        expect(wrapper.find(roleButton).text()).toBe('sw-users-permissions.roles.grid.reviewIncludedAccess count=1');
    });
});
