/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import 'src/app/store/user-verification.store';

const userVerificationService = {
    complete: jest.fn(),
    cancel: jest.fn(),
    switchToSso: jest.fn(() => Promise.resolve()),
};

async function createWrapper() {
    const wrapper = mount(await wrapTestComponent('sw-user-verification', { sync: true }), {
        global: {
            provide: { userVerificationService },
            stubs: {
                // the real modal is covered by its own spec; this one only emits what the host has to handle
                'sw-verify-user-modal': {
                    name: 'sw-verify-user-modal',
                    props: ['ssoAvailable'],
                    emits: [
                        'verified',
                        'verify-with-sso',
                        'close',
                    ],
                    template: `
                        <div class="sw-verify-user-modal-stub" :data-sso-available="ssoAvailable">
                            <button class="emit-verified" @click="$emit('verified', { verified: true })"></button>
                            <button class="emit-verify-with-sso" @click="$emit('verify-with-sso')"></button>
                            <button class="emit-close" @click="$emit('close')"></button>
                        </div>
                    `,
                },
            },
        },
    });
    await flushPromises();

    return wrapper;
}

describe('src/app/component/structure/sw-user-verification', () => {
    const store = () => Shopware.Store.get('userVerification');

    beforeEach(() => {
        store().dismiss();
        jest.clearAllMocks();
    });

    it('renders the modal only while a verification is requested', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-verify-user-modal-stub').exists()).toBe(false);

        store().request(true);
        await flushPromises();

        expect(wrapper.find('.sw-verify-user-modal-stub').attributes('data-sso-available')).toBe('true');

        store().dismiss();
        await flushPromises();

        expect(wrapper.find('.sw-verify-user-modal-stub').exists()).toBe(false);
    });

    it('passes the verified context to the service', async () => {
        const wrapper = await createWrapper();
        store().request(false);
        await flushPromises();

        await wrapper.find('.emit-verified').trigger('click');

        expect(userVerificationService.complete).toHaveBeenCalledWith({ verified: true });
    });

    it('cancels the verification when the modal closes', async () => {
        const wrapper = await createWrapper();
        store().request(false);
        await flushPromises();

        await wrapper.find('.emit-close').trigger('click');

        expect(userVerificationService.cancel).toHaveBeenCalledTimes(1);
    });

    it('switches to the identity provider on request', async () => {
        const wrapper = await createWrapper();
        store().request(true);
        await flushPromises();

        await wrapper.find('.emit-verify-with-sso').trigger('click');

        expect(userVerificationService.switchToSso).toHaveBeenCalledTimes(1);
    });
});
