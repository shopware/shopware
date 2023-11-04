import i18n from 'vue-i18n';
import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/app/component/usage-data/sw-settings-usage-data-modal';
import 'src/app/component/base/sw-icon';
import 'src/app/component/base/sw-alert';
import 'src/app/component/base/sw-modal';
import 'src/app/component/base/sw-button';

import { ALLOW_USAGE_DATA_SYSTEM_CONFIG_KEY } from 'src/core/service/api/metrics.api.service';

async function createWrapper(
    isLoggedIn = true,
    isAdmin = true,
    needApproval = true,
) {
    const localVue = createLocalVue();
    localVue.use(i18n);

    return shallowMount(await Shopware.Component.build('sw-settings-usage-data-modal'), {
        localVue,
        stubs: {
            'sw-icon': await Shopware.Component.build('sw-icon'),
            'sw-alert': await Shopware.Component.build('sw-alert'),
            'sw-modal': await Shopware.Component.build('sw-modal'),
            'sw-button': await Shopware.Component.build('sw-button'),

            'sw-settings-usage-data-intro': true,
        },

        provide: {
            acl: {
                isAdmin: () => isAdmin,
            },
            systemConfigApiService: {
                saveValues: () => Promise.resolve(),
            },
            loginService: {
                isLoggedIn: () => isLoggedIn,
            },
            metricsService: {
                needsApproval: () => Promise.resolve(needApproval),
            },
            shortcutService: {
                startEventListener: () => {},
                stopEventListener: () => {},
            },
        },
    });
}

function modalExists(wrapper) {
    return wrapper.vm.isVisible;
}

function expectThatModalIsOpen(wrapper) {
    expect(modalExists(wrapper)).toBe(true);
}

function expectThatModalIsNotOpen(wrapper) {
    expect(modalExists(wrapper)).toBe(false);
}

describe('src/app/component/usage-data/sw-settings-usage-data-modal', () => {
    let wrapper = null;

    afterEach(() => {
        if (wrapper) {
            wrapper.destroy();
        }
    });

    // eslint-disable-next-line jest/expect-expect
    it('should open the modal if the user is logged in, the user is an admin and the approval request is needed', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        expectThatModalIsOpen(wrapper);
    });

    it('should save the system config value and close when the decline button is clicked', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const saveSystemConfigSpy = jest.spyOn(wrapper.vm.systemConfigApiService, 'saveValues');

        expectThatModalIsOpen(wrapper);

        const declineButton = await wrapper.find('.sw-settings-data-usage-modal__decline-button');
        await declineButton.trigger('click');

        expect(saveSystemConfigSpy).toHaveBeenCalledTimes(1);
        expect(saveSystemConfigSpy).toHaveBeenCalledWith({ [ALLOW_USAGE_DATA_SYSTEM_CONFIG_KEY]: false });

        expectThatModalIsNotOpen(wrapper);
    });

    it('should save the system config value and close when the accept button is clicked', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const saveSystemConfigSpy = jest.spyOn(wrapper.vm.systemConfigApiService, 'saveValues');

        expectThatModalIsOpen(wrapper);

        const acceptButton = await wrapper.find('.sw-settings-data-usage-modal__accept-button');
        await acceptButton.trigger('click');

        expect(saveSystemConfigSpy).toHaveBeenCalledTimes(1);
        expect(saveSystemConfigSpy).toHaveBeenCalledWith({ [ALLOW_USAGE_DATA_SYSTEM_CONFIG_KEY]: true });

        expectThatModalIsNotOpen(wrapper);
    });

    // eslint-disable-next-line jest/expect-expect
    it('should not open the modal if the user is not logged in', async () => {
        wrapper = await createWrapper(false);
        await flushPromises();

        expectThatModalIsNotOpen(wrapper);
    });

    // eslint-disable-next-line jest/expect-expect
    it('should not open the modal if the user is not an admin', async () => {
        wrapper = await createWrapper(true, false);
        await flushPromises();

        expectThatModalIsNotOpen(wrapper);
    });

    // eslint-disable-next-line jest/expect-expect
    it('should not open the modal if the approval request is not needed', async () => {
        wrapper = await createWrapper(true, true, false);
        await flushPromises();

        expectThatModalIsNotOpen(wrapper);
    });
});
