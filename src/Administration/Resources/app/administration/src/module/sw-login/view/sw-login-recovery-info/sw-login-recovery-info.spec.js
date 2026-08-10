/**
 * @sw-package framework
 */

import { config, mount } from '@vue/test-utils';

async function createWrapper() {
    // delete global $router and $routes mocks
    delete config.global.mocks.$router;
    delete config.global.mocks.$route;

    return mount(await wrapTestComponent('sw-login-recovery-info', { sync: true }), {
        global: {
            mocks: {
                $t: (...args) => JSON.stringify([...args]),
            },
            stubs: {
                'router-view': true,
                'router-link': true,
            },
        },
    });
}

describe('module/sw-login/recovery-info.spec.js', () => {
    beforeEach(() => {
        window.history.replaceState({}, '');
    });

    it('should display the email sent confirmation with the submitted email address', async () => {
        window.history.replaceState({ email: 'test@example.com' }, '');

        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.get('.sw-login__status-headline').text()).toBe('["sw-login.recovery.info.headline"]');
        expect(wrapper.get('.sw-login__status-icon').exists()).toBe(true);
        expect(wrapper.get('.sw-login__content-description').text()).toBe(
            '["sw-login.recovery.info.info",{"email":"test@example.com"}]',
        );
    });

    it('should fall back to a generic text when no email address was passed', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.get('.sw-login__status-headline').text()).toBe('["sw-login.recovery.info.headline"]');
        expect(wrapper.get('.sw-login__content-description').text()).toBe(
            '["sw-login.recovery.info.info",{"email":"[\\"sw-login.recovery.info.emailFallback\\"]"}]',
        );
    });

    it('should emit is-not-loading on creation', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.emitted('is-not-loading')).toBeTruthy();
    });
});
