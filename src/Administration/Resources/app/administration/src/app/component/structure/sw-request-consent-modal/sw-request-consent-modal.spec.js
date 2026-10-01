/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import useConsentStore from 'src/core/consent/consent.store';
import { sendConsentRequestResponse } from 'src/core/consent/sdk-handler';
import swRequestConsentModal from './index';

jest.mock('src/core/consent/sdk-handler', () => ({
    sendConsentRequestResponse: jest.fn(),
}));

const defaultConsents = {
    test_consent: {
        name: 'test_consent',
        identifier: 'user-id',
        scopeName: 'admin_user',
        status: 'unset',
        actor: null,
        updatedAt: null,
        acceptedRevision: null,
        latestRevision: null,
    },
    second_consent: {
        name: 'second_consent',
        identifier: 'user-id',
        scopeName: 'admin_user',
        status: 'unset',
        actor: null,
        updatedAt: null,
        acceptedRevision: null,
        latestRevision: null,
    },
};

async function createWrapper() {
    return mount(swRequestConsentModal, {
        global: {
            mocks: {
                $t: (snippet) => snippet,
            },
            stubs: {
                teleport: {
                    template: '<div><slot/></div>',
                },
            },
        },
    });
}

function addConsentRequest(consent = 'test_consent') {
    useConsentStore().addConsentRequest(
        {
            consent,
            requestId: 'request-id',
            requestMessage: 'Please allow analytics',
            privacyLink: 'https://example.com/privacy',
        },
        {
            extensionName: 'test-app',
            origin: 'https://example.com',
            window,
        },
    );
}

function addConsentRequestWithoutOptionalFields(consent = 'test_consent') {
    useConsentStore().addConsentRequest(
        {
            consent,
            requestId: 'request-id',
        },
        {
            extensionName: 'test-app',
            origin: 'https://example.com',
            window,
        },
    );
}

describe('src/app/component/structure/sw-request-consent-modal', () => {
    let wrapper = null;

    beforeEach(() => {
        const consentStore = useConsentStore();
        consentStore.$reset();
        consentStore.consents = structuredClone(defaultConsents);
        jest.clearAllMocks();
    });

    afterEach(async () => {
        if (wrapper) {
            await wrapper.unmount();
            wrapper = null;
        }
    });

    it('does not show the modal if there is no consent request', async () => {
        wrapper = await createWrapper();

        expect(wrapper.find('.mt-modal').exists()).toBe(false);
    });

    it('shows the modal if there is a consent request', async () => {
        addConsentRequest();

        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.mt-modal').exists()).toBe(true);
    });

    it('shows the request message when it exists on the consent request', async () => {
        addConsentRequest();

        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-request-consent-modal__requestMessage').exists()).toBe(true);
    });

    it('shows the privacy link when it exists on the consent request', async () => {
        addConsentRequest();

        wrapper = await createWrapper();
        await flushPromises();

        const privacyLink = wrapper.find('.sw-request-consent-modal a');

        expect(privacyLink.exists()).toBe(true);
        expect(privacyLink.text()).toBe('https://example.com/privacy');
    });

    it('hides the request message and privacy link when they are not provided', async () => {
        addConsentRequestWithoutOptionalFields();

        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-request-consent-modal__requestMessage').exists()).toBe(false);
        expect(wrapper.find('.sw-request-consent-modal a').exists()).toBe(false);
    });

    it('sends a consent request response when consent is accepted', async () => {
        const consentStore = useConsentStore();
        addConsentRequest();

        jest.spyOn(consentStore, 'accept').mockImplementation(async (name) => {
            consentStore.consents[name] = {
                ...consentStore.consents[name],
                status: 'accepted',
            };
        });

        wrapper = await createWrapper();

        await wrapper.vm.accept();
        await flushPromises();

        expect(sendConsentRequestResponse).toHaveBeenCalledWith(
            window,
            'request-id',
            expect.objectContaining({
                name: 'test_consent',
                status: 'accepted',
            }),
        );
    });

    it('sends a consent request response when consent is declined', async () => {
        const consentStore = useConsentStore();
        addConsentRequest();

        jest.spyOn(consentStore, 'revoke').mockImplementation(async (name) => {
            consentStore.consents[name] = {
                ...consentStore.consents[name],
                status: 'revoked',
            };
        });

        wrapper = await createWrapper();

        await wrapper.vm.decline();
        await flushPromises();

        expect(sendConsentRequestResponse).toHaveBeenCalledWith(
            window,
            'request-id',
            expect.objectContaining({
                name: 'test_consent',
                status: 'revoked',
            }),
        );
    });

    it('stays open if multiple consent requests are in the store', async () => {
        const consentStore = useConsentStore();
        addConsentRequest('test_consent');
        addConsentRequest('second_consent');

        jest.spyOn(consentStore, 'accept').mockImplementation(async (name) => {
            consentStore.consents[name] = {
                ...consentStore.consents[name],
                status: 'accepted',
            };
        });

        wrapper = await createWrapper();

        expect(consentStore.consentRequestInfo).toHaveLength(2);

        await wrapper.vm.accept();
        await flushPromises();

        expect(consentStore.consentRequestInfo).toHaveLength(1);
        expect(wrapper.find('.mt-modal').exists()).toBe(true);
    });
});
