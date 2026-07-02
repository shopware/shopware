/**
 * @sw-package fundamentals@framework
 */

import { mount } from '@vue/test-utils';

async function createWrapper(props = {}) {
    return mount(await wrapTestComponent('sw-users-permissions-user-sso-managed-roles', { sync: true }), {
        props,
        global: {
            mocks: {
                $t: (key: string, params?: Record<string, unknown>) => (params ? `${key}|${JSON.stringify(params)}` : key),
            },
        },
    });
}

describe('module/sw-users-permissions/components/sw-users-permissions-user-sso-managed-roles', () => {
    it('names the providers in the info banner', async () => {
        const wrapper = await createWrapper({
            providerLabels: [
                'Corporate SSO',
                'Backup IdP',
            ],
        });

        expect(wrapper.get('.sw-users-permissions-user-sso-managed-roles__banner').text()).toContain(
            '{"providers":"Corporate SSO, Backup IdP"}',
        );
    });

    it('falls back to a generic provider name when no provider label resolves', async () => {
        const wrapper = await createWrapper({ providerLabels: [] });

        expect(wrapper.get('.sw-users-permissions-user-sso-managed-roles__banner').text()).toContain(
            'sw-users-permissions.ssoManaged.fallbackProvider',
        );
    });

    it('renders a read-only badge per managed role', async () => {
        const wrapper = await createWrapper({
            roles: [
                { id: 'role-1', name: 'Catalog editor' },
                { id: 'role-2', name: 'Order manager' },
            ],
        });

        const badges = wrapper.findAll('.sw-users-permissions-user-sso-managed-roles__badge');
        expect(badges).toHaveLength(2);
        expect(badges[0].text()).toBe('Catalog editor');
        expect(badges[1].text()).toBe('Order manager');
    });

    it('renders an admin badge when the admin flag is SSO-managed', async () => {
        const wrapper = await createWrapper({ ssoManagedAdmin: true });

        expect(wrapper.get('.sw-users-permissions-user-sso-managed-roles__badge--admin').text()).toBe(
            'sw-users-permissions.ssoManaged.adminBadge',
        );
    });

    it('hides the badge list when nothing is managed', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-users-permissions-user-sso-managed-roles__list').exists()).toBe(false);
    });
});
