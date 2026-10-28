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
                'sw-verify-user-modal': {
                    name: 'sw-verify-user-modal',
                    template: '<div class="sw-verify-user-modal-stub"></div>',
                    props: ['ssoAvailable'],
                    emits: [
                        'verified',
                        'verify-with-sso',
                        'close',
                    ],
                },
            },
        },
    });
    await flushPromises();

    return wrapper;
}

function emitFromModal(wrapper: Awaited<ReturnType<typeof createWrapper>>, event: string, ...args: unknown[]): void {
    (
        wrapper.findComponent({ name: 'sw-verify-user-modal' }).vm as { $emit: (event: string, ...args: unknown[]) => void }
    ).$emit(event, ...args);
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

        const modal = wrapper.findComponent({ name: 'sw-verify-user-modal' });
        expect(modal.exists()).toBe(true);
        expect(modal.props('ssoAvailable')).toBe(true);

        store().dismiss();
        await flushPromises();

        expect(wrapper.find('.sw-verify-user-modal-stub').exists()).toBe(false);
    });

    it('passes the verified context to the service', async () => {
        const wrapper = await createWrapper();
        store().request(false);
        await flushPromises();

        const context = { ...Shopware.Context.api };
        emitFromModal(wrapper, 'verified', context);

        expect(userVerificationService.complete).toHaveBeenCalledWith(context);
    });

    it('cancels the verification when the modal closes', async () => {
        const wrapper = await createWrapper();
        store().request(false);
        await flushPromises();

        emitFromModal(wrapper, 'close');

        expect(userVerificationService.cancel).toHaveBeenCalledTimes(1);
    });

    it('switches to the identity provider on request', async () => {
        const wrapper = await createWrapper();
        store().request(true);
        await flushPromises();

        emitFromModal(wrapper, 'verify-with-sso');

        expect(userVerificationService.switchToSso).toHaveBeenCalledTimes(1);
    });
});
