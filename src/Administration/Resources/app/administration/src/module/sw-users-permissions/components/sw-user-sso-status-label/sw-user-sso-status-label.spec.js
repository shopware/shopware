/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

async function createWrapper(user) {
    return mount(
        await wrapTestComponent('sw-user-sso-status-label', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-color-badge': true,
                },
            },
            props: {
                user: user,
            },
        },
    );
}

describe('module/sw-users-permissions/components/sw-user-sso-status-label', () => {
    it('should be active', async () => {
        const wrapper = await createWrapper({ active: true });

        const colorBadge = await wrapper.find('.sw-user-sso-status-label');
        expect(colorBadge.attributes('variant')).toBe('positive');

        expect(colorBadge.text()).toBe('sw-users-permissions.sso.user-listing.status-label.active');
    });

    it('should be invited', async () => {
        const wrapper = await createWrapper({
            active: false,
            email: 'foo@bar.baz',
            firstName: 'foo@bar.baz',
            lastName: 'foo@bar.baz',
        });

        const colorBadge = await wrapper.find('.sw-user-sso-status-label');
        expect(colorBadge.attributes('variant')).toBe('attention');

        expect(colorBadge.text()).toBe('sw-users-permissions.sso.user-listing.status-label.invited');
    });

    it('should be inactive', async () => {
        const wrapper = await createWrapper({
            active: false,
            email: 'foo@bar.baz',
            firstName: 'foo',
            lastName: 'bar',
        });

        const colorBadge = await wrapper.find('.sw-user-sso-status-label');
        expect(colorBadge.attributes('variant')).toBe('critical');

        expect(colorBadge.text()).toBe('sw-users-permissions.sso.user-listing.status-label.inactive');
    });
});
