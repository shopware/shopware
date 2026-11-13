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

const translate = (key: string, params: Record<string, unknown> = {}) => {
    return Object.entries(params).reduce((text, [name, value]) => `${text} ${name}=${String(value)}`, key);
};

async function createWrapper(rolePrivileges: string[] = []) {
    const privilegesService = new PrivilegesService();
    mappings.forEach((mapping) => privilegesService.addPrivilegeMappingEntry(mapping));
    const routerPush = jest.fn();

    const wrapper = mount(await wrapTestComponent('sw-users-permissions-permissions-grid', { sync: true }), {
        global: {
            renderStubDefaultSlot: true,
            stubs: {
                // Renders the key and the slot, because the test setup has no snippets to interpolate
                'i18n-t': {
                    props: ['keypath'],
                    template: '<p>{{ keypath }} <slot name="areas" /></p>',
                },
            },
            provide: { privileges: privilegesService },
            mocks: {
                $t: translate,
                $tc: translate,
                $router: { push: routerPush },
                $route: { params: { id: 'role-id' } },
            },
        },
        props: reactive({ role: { privileges: rolePrivileges } }),
    });

    await flushPromises();

    return { wrapper, privilegesService, routerPush };
}

const row = (key: string) => `.sw-users-permissions-permissions-grid__entry_${key}`;
const chip = (key: string) => `${row(key)} .sw-users-permissions-permissions-grid__hidden-access-chip`;
const grantedVia = (key: string) => `${row(key)} .sw-users-permissions-permissions-grid__granted-via`;
const grantedViaTitle = 'sw-users-permissions.roles.grid.grantedViaTitle';
const grantedViaText = (wrapper: ReturnType<typeof mount>, key: string) => {
    return wrapper.find(grantedVia(key)).attributes('aria-label');
};
const details = (key: string) => `${row(key)} .sw-users-permissions-permissions-grid__hidden-access-details`;
const groups = (wrapper: ReturnType<typeof mount>, key: string) => {
    return wrapper.findAll(`${details(key)} .sw-users-permissions-permissions-grid__hidden-access-group`);
};
const groupTooltip = (group: ReturnType<ReturnType<typeof mount>['find']>) => {
    return group.find('.sw-users-permissions-permissions-grid__info-icon').attributes('aria-label');
};
const attentionLabel = '.sw-users-permissions-permissions-grid__attention-label';
const banner = '.sw-users-permissions-permissions-grid__personal-data-banner';
const bannerText = `${banner} .sw-users-permissions-permissions-grid__personal-data-text`;
const checkbox = (key: string, role: string) => {
    return `${row(key)} .sw-users-permissions-permissions-grid__role_${role} .mt-field--checkbox__container input`;
};

describe('src/module/sw-users-permissions/components/sw-users-permissions-permissions-grid hidden access', () => {
    it.each([
        'country',
        'tag',
        'promotion',
    ])('should show a personal data chip on the %s row', async (key) => {
        const { wrapper } = await createWrapper();

        const hiddenAccessChip = wrapper.find(chip(key));

        expect(hiddenAccessChip.classes()).toContain('is--attention');
        expect(hiddenAccessChip.find('.mt-icon').exists()).toBe(true);
    });

    it('should show a chip without the attention style for plain reads on the product row', async () => {
        const { wrapper } = await createWrapper();

        const hiddenAccessChip = wrapper.find(chip('product'));

        expect(hiddenAccessChip.text()).toBe('sw-users-permissions.roles.grid.hiddenAccessChip count=1');
        expect(hiddenAccessChip.classes()).not.toContain('is--attention');
        expect(hiddenAccessChip.find('.mt-icon').exists()).toBe(false);
    });

    it('should show plain reads without an attention label', async () => {
        const { wrapper } = await createWrapper(['product.viewer']);

        await wrapper.find(chip('product')).trigger('click');

        const [mediaGroup] = groups(wrapper, 'product');
        expect(mediaGroup.classes()).not.toContain('is--attention');
        expect(mediaGroup.find('strong').text()).toBe('sw-privileges.permissions.media.label');
        expect(mediaGroup.find(attentionLabel).exists()).toBe(false);
        expect(groupTooltip(mediaGroup)).toBe('sw-privileges.permissionType.read media');
    });

    it('should mark write access on another area for attention', async () => {
        const { wrapper } = await createWrapper(['promotion.editor']);

        expect(wrapper.find(chip('promotion')).classes()).toContain('is--attention');

        await wrapper.find(chip('promotion')).trigger('click');

        const ruleGroup = groups(wrapper, 'promotion').find((group) => {
            return group.find('strong').text() === 'sw-privileges.permissions.rule.label';
        });
        expect(ruleGroup?.classes()).toContain('is--attention');
        expect(ruleGroup?.find(attentionLabel).text()).toBe('sw-users-permissions.roles.grid.writeAccess');
        expect(groupTooltip(ruleGroup!)).toBe('sw-privileges.permissionType.read, sw-privileges.permissionType.update rule');
    });

    it('should merge granted via notes of operations reached through the same modules', async () => {
        const { wrapper } = await createWrapper(['promotion.editor']);

        expect(grantedViaText(wrapper, 'rule')).toBe(
            `${grantedViaTitle} sw-users-permissions.roles.grid.grantedViaEntry ` +
                'operation=sw-privileges.permissionType.read, sw-privileges.permissionType.update ' +
                'modules=sw-privileges.permissions.promotion.label',
        );
    });

    it('should not show a chip on rows without hidden access', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.find(chip('customer')).exists()).toBe(false);
        expect(wrapper.find(chip('media')).exists()).toBe(false);
    });

    it('should list the owning modules and operations when the chip is opened', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.find(details('tag')).exists()).toBe(false);

        await wrapper.find(chip('tag')).trigger('click');

        const tagGroups = groups(wrapper, 'tag');
        expect(wrapper.find(chip('tag')).attributes('aria-expanded')).toBe('true');
        expect(tagGroups).toHaveLength(2);
        expect(tagGroups[0].find('strong').text()).toBe('sw-privileges.permissions.customer.label');
        expect(tagGroups[0].find(attentionLabel).text()).toBe('sw-users-permissions.roles.grid.personalData');
        expect(groupTooltip(tagGroups[0])).toBe('sw-privileges.permissionType.read customer');
        expect(tagGroups[1].find('strong').text()).toBe('sw-privileges.permissions.order.label');

        await wrapper.find(chip('tag')).trigger('click');

        expect(wrapper.find(details('tag')).exists()).toBe(false);
    });

    it('should show the granted via note on the customer row only for indirect access', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.find(grantedVia('customer')).exists()).toBe(false);

        await wrapper.find<HTMLInputElement>(checkbox('country', 'viewer')).setValue(true);
        await wrapper.find<HTMLInputElement>(checkbox('tag', 'viewer')).setValue(true);

        expect(grantedViaText(wrapper, 'customer')).toBe(
            `${grantedViaTitle} sw-users-permissions.roles.grid.grantedViaEntry ` +
                'operation=sw-privileges.permissionType.read ' +
                'modules=sw-privileges.permissions.country.label, sw-privileges.permissions.tag.label',
        );

        // The customer module grants customer:read itself, so nothing is hidden anymore
        await wrapper.find<HTMLInputElement>(checkbox('customer', 'viewer')).setValue(true);

        expect(wrapper.find(grantedVia('customer')).exists()).toBe(false);
    });

    it('should show and hide the personal data banner as boxes are ticked', async () => {
        const { wrapper, routerPush } = await createWrapper();

        expect(wrapper.find(banner).exists()).toBe(false);

        await wrapper.find<HTMLInputElement>(checkbox('promotion', 'viewer')).setValue(true);

        expect(wrapper.find(bannerText).text()).toBe(
            'sw-users-permissions.roles.grid.personalDataBannerText sw-privileges.permissions.customer.label (customer:read)',
        );
        expect(wrapper.find(`${bannerText} strong`).text()).toBe('sw-privileges.permissions.customer.label');

        await wrapper.find<HTMLInputElement>(checkbox('tag', 'viewer')).setValue(true);

        expect(wrapper.find(bannerText).text()).toContain('sw-privileges.permissions.order.label (order:read)');

        await wrapper.find(`${banner} .sw-users-permissions-permissions-grid__personal-data-link`).trigger('click');

        expect(routerPush).toHaveBeenCalledWith({
            name: 'sw.users.permissions.role.detail.detailed-privileges',
            params: { id: 'role-id' },
        });

        await wrapper.find<HTMLInputElement>(checkbox('promotion', 'viewer')).setValue(false);
        await wrapper.find<HTMLInputElement>(checkbox('tag', 'viewer')).setValue(false);

        expect(wrapper.find(banner).exists()).toBe(false);
    });

    it('should not show the banner for hidden access without personal data', async () => {
        const { wrapper } = await createWrapper(['product.viewer']);

        expect(wrapper.find(grantedVia('media')).exists()).toBe(true);
        expect(wrapper.find(banner).exists()).toBe(false);
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

        expect(wrapper.find(chip('swag_loyalty')).classes()).toContain('is--attention');

        await wrapper.find<HTMLInputElement>(checkbox('swag_loyalty', 'viewer')).setValue(true);

        expect(grantedViaText(wrapper, 'order')).toBe(
            `${grantedViaTitle} sw-users-permissions.roles.grid.grantedViaEntry ` +
                'operation=sw-privileges.permissionType.read modules=sw-privileges.permissions.swag_loyalty.label',
        );
        expect(wrapper.find(bannerText).text()).toContain('sw-privileges.permissions.order.label (order:read)');
    });
});
