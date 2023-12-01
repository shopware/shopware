// eslint-disable-next-line filename-rules/match
import { shallowMount } from '@vue/test-utils';
import swUsageDataConsentBanner from 'src/module/sw-settings-usage-data/component/sw-usage-data-consent-banner';
import swButton from 'src/app/component/base/sw-button';
import enGB from 'src/module/sw-dashboard/snippet/en-GB.json';

Shopware.Component.register('sw-usage-data-consent-banner', swUsageDataConsentBanner);
Shopware.Component.register('sw-button', swButton);

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
    return shallowMount(await Shopware.Component.build('sw-usage-data-consent-banner'), {
        propsData: {
            canBeHidden,
        },
        stubs: {
            'sw-button': await Shopware.Component.build('sw-button'),
            'sw-external-link': true,
            'sw-internal-link': true,
            'sw-icon': true,
            'sw-help-text': true,
            i18n: true,
        },
        mocks: {
            $tc: (...args) => JSON.stringify([...args]),
            $i18n: {
                locale: 'en-GB',
                messages: {
                    'en-GB': enGB,
                },
            },
        },
        provide: {
            usageDataService,
            acl: {
                can: () => isPrivileged,
            },
        },
    });
}

/**
 * @package merchant-services
 */
describe('module/sw-settings-usage-data/component/sw-usage-data-consent-banner', () => {
    let wrapper;

    beforeEach(async () => {
        Shopware.State.commit('usageData/updateConsent', {
            isConsentGiven: false,
            isBannerHidden: false,
        });
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should show the usage data consent banner', async () => {
        wrapper = await createWrapper();

        expect(wrapper.find('.sw-usage-data-consent-banner').exists()).toBeTruthy();
    });

    it('should hide the banner and show the hint linking to the settings page after rejecting', async () => {
        const hideBannerSpy = jest.spyOn(usageDataService, 'hideBanner');

        wrapper = await createWrapper(true);
        const declineButton = wrapper.get('.sw-usage-data-consent-banner__decline-button');

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
        const declineButton = wrapper.get('.sw-usage-data-consent-banner__accept-button');

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
        const declineButton = wrapper.get('.sw-usage-data-consent-banner__reject-button');

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
