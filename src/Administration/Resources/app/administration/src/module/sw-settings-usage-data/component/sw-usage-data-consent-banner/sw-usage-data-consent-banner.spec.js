import { mount } from '@vue/test-utils';

const usageDataService = {
    getConsent: () => jest.fn(),
    acceptConsent: () => jest.fn(),
    revokeConsent: () => jest.fn(),
    hideBanner: () => jest.fn(),
};

/**
 * @package data-services
 */
async function createWrapper(canBeHidden = false, isPrivileged = true) {
    const wrapper = mount(await wrapTestComponent('sw-usage-data-consent-banner', {
        sync: true,
    }), {
        props: {
            canBeHidden,
        },
        global: {
            stubs: {
                'sw-icon': await wrapTestComponent('sw-icon', { sync: true }),
                'sw-button': await wrapTestComponent('sw-button', { sync: true }),
                'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated', { sync: true }),
                'sw-external-link': true,
                'sw-internal-link': true,
                'sw-help-text': true,
                i18n: true,
                'i18n-t': true,
                'sw-icon-deprecated': true,
                'router-link': true,
                'sw-loader': true,
            },
            provide: {
                usageDataService,
                acl: {
                    can: () => isPrivileged,
                },
            },
        },
    });

    await flushPromises();

    return wrapper;
}

describe('src/module/sw-settings-usage-data/component/sw-usage-data-consent-banner', () => {
    let wrapper = null;

    beforeEach(async () => {
        if (Shopware.State.get('usageData')) {
            Shopware.State.commit('usageData/updateConsent', {
                isConsentGiven: false,
                isBannerHidden: false,
            });
        }
    });

    it('should show the usage data consent banner', async () => {
        wrapper = await createWrapper();

        expect(wrapper.find('.sw-usage-data-consent-banner').exists()).toBeTruthy();
    });

    it('should hide the banner and show the hint linking to the settings page after rejecting', async () => {
        const hideBannerSpy = jest.spyOn(usageDataService, 'hideBanner');

        wrapper = await createWrapper(true);
        const declineButton = wrapper.get('.sw-usage-data-consent-banner__footer-decline-button');

        await declineButton.trigger('click');

        expect(hideBannerSpy).toHaveBeenCalled();
        expect(wrapper.get('.sw-usage-data-consent-banner-reject-accept-message').isVisible()).toBe(true);

        const closeButton = wrapper.get('.sw-usage-data-consent-banner-reject-accept-message__close');
        await closeButton.trigger('click');

        expect(wrapper.isVisible()).toBe(false);
    });

    it('should hide the banner and show the thank you message after accepting', async () => {
        const acceptConsentSpy = jest.spyOn(usageDataService, 'acceptConsent');

        wrapper = await createWrapper(true);
        const declineButton = wrapper.get('.sw-usage-data-consent-banner__footer-accept-button');

        await declineButton.trigger('click');

        expect(acceptConsentSpy).toHaveBeenCalled();
        expect(wrapper.isVisible()).toBe(true);

        const closeButton = wrapper.get('.sw-usage-data-consent-banner-reject-accept-message__close');
        await closeButton.trigger('click');

        expect(wrapper.isVisible()).toBe(false);
    });

    it('should reject the consent when the reject button is clicked', async () => {
        Shopware.State.commit('usageData/updateIsConsentGiven', true);

        const revokeConsentSpy = jest.spyOn(usageDataService, 'revokeConsent');

        wrapper = await createWrapper();
        const declineButton = wrapper.get('.sw-usage-data-consent-banner__footer-reject-button');

        await declineButton.trigger('click');

        expect(revokeConsentSpy).toHaveBeenCalled();
        expect(wrapper.isVisible()).toBe(true);
    });

    it('can not be hidden by default', async () => {
        wrapper = await createWrapper();

        expect(wrapper.props('canBeHidden')).toBe(false);
    });

    it('can be hidden', async () => {
        wrapper = await createWrapper(true);

        expect(wrapper.props('canBeHidden')).toBe(true);
    });

    it('should not show the banner if the user is not privileged', async () => {
        wrapper = await createWrapper(true, false);

        expect(wrapper.isVisible()).toBe(false);
    });

    it('should show the banner if the user is privileged', async () => {
        wrapper = await createWrapper(true, true);

        expect(wrapper.isVisible()).toBe(true);
    });
});
