import { config, shallowMount } from '@vue/test-utils';

import 'src/module/sw-login/view/sw-login-recovery-info';
import 'src/app/component/base/sw-alert';

function hasNormalWarningAlert(wrapper) {
    const alerts = wrapper.findAll('.sw-alert');

    expect(alerts.length).toBe(2);
    expect(alerts.at(0).text()).toBe('["sw-login.recovery.info.info"]');
    expect(alerts.at(1).text()).toBe('["sw-login.recovery.info.warning"]');
}

function createWrapper(routeParams) {
    // delete global $router and $routes mocks
    delete config.mocks.$router;
    delete config.mocks.$route;

    return shallowMount(Shopware.Component.build('sw-login-recovery-info'), {
        mocks: {
            $tc: (...args) => JSON.stringify([...args]),
            $route: { params: routeParams }
        },
        stubs: {
            'router-view': true,
            'router-link': true,
            'sw-alert': Shopware.Component.build('sw-alert'),
            'sw-icon': true
        }
    });
}

describe('module/sw-login/recovery-info.spec.js', () => {
    it('should be a Vue.js component', () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should display the normal info', () => {
        const wrapper = createWrapper();

        expect(wrapper.get('.sw-login__form-headline').text()).toBe('["sw-login.recovery.info.headline"]');

        hasNormalWarningAlert(wrapper);

        const timeWrapper = createWrapper();

        expect(timeWrapper.get('.sw-login__form-headline').text()).toBe('["sw-login.recovery.info.headline"]');

        hasNormalWarningAlert(timeWrapper);
    });

    it('should display the rate limit info', () => {
        const wrapper = createWrapper({
            waitTime: 1
        });

        expect(wrapper.get('.sw-login__form-headline').text()).toBe('["sw-login.recovery.info.headline"]');

        const alerts = wrapper.findAll('.sw-alert');

        expect(alerts.length).toBe(1);
        expect(alerts.at(0).text()).toBe('["global.error-codes.FRAMEWORK__RATE_LIMIT_EXCEEDED",0,{"seconds":1}]');
    });
});
