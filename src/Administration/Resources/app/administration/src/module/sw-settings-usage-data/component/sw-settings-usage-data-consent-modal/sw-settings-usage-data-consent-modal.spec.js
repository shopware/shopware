import { mount } from '@vue/test-utils';
import {
    MtSwitch,
    MtModal,
    MtModalClose,
    MtModalAction,
    MtModalTrigger,
    MtModalRoot,
} from '@shopware-ag/meteor-component-library';
import useConsentStore from 'src/core/consent/consent.store';
import swSettingsUsageDataConsentModal from './index';

function createConsentModal(storeDataConsent, userDataConsent) {
    return mount(swSettingsUsageDataConsentModal, {
        props: {
            storedStoreDataConsent: storeDataConsent,
            storedUserDataConsent: userDataConsent,
        },
        global: {
            stubs: {
                Teleport: { template: '<div><slot /></div>' },
                'mt-modal': MtModal,
                'mt-modal-close': MtModalClose,
                'mt-modal-action': MtModalAction,
                'mt-modal-trigger': MtModalTrigger,
                'mt-modal-root': MtModalRoot,
            },
        },
    });
}

describe('/module/sw-settings-usage-data/component/sw-settings-usage-data-consent-modal', () => {
    beforeEach(() => {
        global.activeAclRoles = [
            'system.system_config',
            'user.update_profile',
        ];
    });

    describe('save preferences', () => {
        it('shows share all/share nothing button when no consent is given', async () => {
            const wrapper = await createConsentModal(false, false);

            const buttons = wrapper.findAll('.mt-modal__footer button');

            expect(buttons).toHaveLength(2);
            expect(buttons[0].text()).toBe('sw-settings-usage-data.consent-modal.actions.share-nothing');
            expect(buttons[1].text()).toBe('sw-settings-usage-data.consent-modal.actions.share-all-data');
        });

        it('shows save preferences when store data consent was given before', async () => {
            const wrapper = await createConsentModal(true, false);

            const buttons = wrapper.findAll('.mt-modal__footer button');

            expect(buttons).toHaveLength(1);
            expect(buttons[0].text()).toBe('sw-settings-usage-data.consent-modal.actions.save-preferences');
        });

        it('shows save preferences when one or both consent states changes', async () => {
            const wrapper = await createConsentModal(false, false);

            const [
                shareStoreDataSwitch,
                shareUserDataSwitch,
            ] = wrapper.findAllComponents(MtSwitch);

            await shareStoreDataSwitch.get('input').trigger('change');

            let savePreferencesButton = wrapper.find('.mt-modal__footer button');

            expect(savePreferencesButton.text()).toBe('sw-settings-usage-data.consent-modal.actions.save-preferences');

            await shareStoreDataSwitch.get('input').trigger('change');
            await shareUserDataSwitch.get('input').trigger('change');

            savePreferencesButton = wrapper.find('.mt-modal__footer button');

            expect(savePreferencesButton.text()).toBe('sw-settings-usage-data.consent-modal.actions.save-preferences');

            await shareStoreDataSwitch.get('input').trigger('change');

            savePreferencesButton = wrapper.find('.mt-modal__footer button');

            expect(savePreferencesButton.text()).toBe('sw-settings-usage-data.consent-modal.actions.save-preferences');
        });
    });

    describe('store data consent', () => {
        it('shows store data consent if user has permissions and consent was not given before', async () => {
            const wrapper = await createConsentModal(false, false);
            const subCardHeadings = wrapper.findAll('.sw-settings-usage-data-consent-modal-sub-card h4');

            expect(subCardHeadings).toHaveLength(2);
            expect(subCardHeadings.map((heading) => heading.text())).toContain(
                'sw-settings-usage-data.consent-modal.store-data.title',
            );
        });

        it('hides store data consent if it was given before', async () => {
            const wrapper = await createConsentModal(true, false);
            const subCardHeadings = wrapper.findAll('.sw-settings-usage-data-consent-modal-sub-card h4');

            expect(subCardHeadings).toHaveLength(1);
            expect(subCardHeadings.map((heading) => heading.text())).not.toContain(
                'sw-settings-usage-data.consent-modal.store-data.title',
            );
        });

        it('hides store data consent if user can not write the system config', async () => {
            global.activeAclRoles = [];

            const wrapper = await createConsentModal(false, false);
            const subCardHeadings = wrapper.findAll('.sw-settings-usage-data-consent-modal-sub-card h4');

            expect(subCardHeadings).toHaveLength(1);
            expect(subCardHeadings.map((heading) => heading.text())).not.toContain(
                'sw-settings-usage-data.consent-modal.store-data.title',
            );
        });
    });

    describe('persist consent', () => {
        it('accepts both consents when "Share All" is clicked', async () => {
            const consentStore = useConsentStore();
            const acceptSpy = jest.spyOn(consentStore, 'accept');
            acceptSpy.mockImplementation(() => Promise.resolve());

            const wrapper = await createConsentModal(false, false);

            const shareAllButton = wrapper.findAll('.mt-modal__footer button')[1];

            await shareAllButton.trigger('click');

            expect(acceptSpy).toHaveBeenCalledTimes(2);
            expect(acceptSpy.mock.calls[0][0]).toBe('backend_data');
            expect(acceptSpy.mock.calls[1][0]).toBe('product_analytics');
        });

        it('revokes both consents when "Share Nothing" is clicked', async () => {
            const consentStore = useConsentStore();
            const revokeSpy = jest.spyOn(consentStore, 'revoke');
            revokeSpy.mockImplementation(() => Promise.resolve());

            const wrapper = await createConsentModal(false, false);

            const shareNothingButton = wrapper.findAll('.mt-modal__footer button')[0];

            await shareNothingButton.trigger('click');

            expect(revokeSpy).toHaveBeenCalledTimes(2);
            expect(revokeSpy.mock.calls[0][0]).toBe('backend_data');
            expect(revokeSpy.mock.calls[1][0]).toBe('product_analytics');
        });

        it('saves preferences as selected', async () => {
            const consentStore = useConsentStore();
            const acceptSpy = jest.spyOn(consentStore, 'accept');
            const revokeSpy = jest.spyOn(consentStore, 'revoke');
            acceptSpy.mockImplementation(() => Promise.resolve());
            revokeSpy.mockImplementation(() => Promise.resolve());

            const wrapper = await createConsentModal(true, false);

            const savePreferencesButton = wrapper.find('.mt-modal__footer button');

            await savePreferencesButton.trigger('click');

            expect(acceptSpy).toHaveBeenCalled();
            expect(revokeSpy).toHaveBeenCalled();
            expect(acceptSpy.mock.calls[0][0]).toBe('backend_data');
            expect(revokeSpy.mock.calls[0][0]).toBe('product_analytics');
        });

        it('does not update backend data consent if permissions are missing', async () => {
            global.activeAclRoles = ['user.update_profile'];

            const consentStore = useConsentStore();
            const acceptSpy = jest.spyOn(consentStore, 'accept');
            const revokeSpy = jest.spyOn(consentStore, 'revoke');
            acceptSpy.mockImplementation(() => Promise.resolve());
            revokeSpy.mockImplementation(() => Promise.resolve());

            const wrapper = await createConsentModal(true, false);

            const savePreferencesButton = wrapper.find('.mt-modal__footer button');

            await savePreferencesButton.trigger('click');

            expect(acceptSpy).not.toHaveBeenCalled();
            expect(revokeSpy).toHaveBeenCalled();
            expect(revokeSpy.mock.calls[0][0]).toBe('product_analytics');
        });

        it('does not update user data consent if permissions are missing', async () => {
            global.activeAclRoles = ['system.system_config'];

            const consentStore = useConsentStore();
            const acceptSpy = jest.spyOn(consentStore, 'accept');
            const revokeSpy = jest.spyOn(consentStore, 'revoke');
            acceptSpy.mockImplementation(() => Promise.resolve());
            revokeSpy.mockImplementation(() => Promise.resolve());

            const wrapper = await createConsentModal(true, false);

            const savePreferencesButton = wrapper.find('.mt-modal__footer button');

            await savePreferencesButton.trigger('click');

            expect(acceptSpy).toHaveBeenCalled();
            expect(revokeSpy).not.toHaveBeenCalled();
            expect(acceptSpy.mock.calls[0][0]).toBe('backend_data');
        });

        it('shows error notification when updating consent fails', async () => {
            const consentStore = useConsentStore();
            const notificationStore = Shopware.Store.get('notification');

            const notificationSpy = jest.spyOn(notificationStore, 'createNotification');
            const acceptSpy = jest.spyOn(consentStore, 'accept');

            acceptSpy.mockImplementation(() => Promise.reject());

            const wrapper = await createConsentModal(true, false);

            const savePreferencesButton = wrapper.find('.mt-modal__footer button');

            await savePreferencesButton.trigger('click');

            expect(acceptSpy).toHaveBeenCalled();
            expect(notificationSpy).toHaveBeenCalledWith({
                variant: 'critical',
                title: 'global.default.error',
                message: 'sw-settings-usage-data.errors.consent-update-error',
            });
        });
    });
});
