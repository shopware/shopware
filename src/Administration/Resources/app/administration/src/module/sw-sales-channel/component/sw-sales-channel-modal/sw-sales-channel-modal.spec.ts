/**
 * @sw-package discovery
 */

import { mount } from '@vue/test-utils';

async function createWrapper(ids: string[]) {
    const productStreamRepository = {
        searchIds: jest.fn(() => Promise.resolve({ total: 0, data: ids })),
    };

    const wrapper = mount(await wrapTestComponent('sw-sales-channel-modal', { sync: true }), {
        global: {
            provide: {
                repositoryFactory: {
                    create: () => productStreamRepository,
                },
            },
            stubs: {
                'sw-modal': {
                    template: '<div class="sw-modal"><slot></slot></div>',
                },
                'sw-sales-channel-modal-grid': {
                    props: ['productStreamsExist', 'productStreamsLoading'],
                    template: `<div
                        class="sw-sales-channel-modal-grid"
                        :data-streams-exist="String(productStreamsExist)"
                        :data-streams-loading="String(productStreamsLoading)"
                    ></div>`,
                },
                'sw-sales-channel-modal-detail': true,
                'mt-button': true,
            },
        },
    });

    return { wrapper, productStreamRepository };
}

describe('src/module/sw-sales-channel/component/sw-sales-channel-modal', () => {
    it('should mark product streams as existing when a non-internal stream is found', async () => {
        const { wrapper, productStreamRepository } = await createWrapper(['product-stream-id']);
        await flushPromises();

        expect(productStreamRepository.searchIds).toHaveBeenCalledTimes(1);
        expect(wrapper.find('.sw-sales-channel-modal-grid').attributes('data-streams-exist')).toBe('true');
        expect(wrapper.find('.sw-sales-channel-modal-grid').attributes('data-streams-loading')).toBe('false');
    });

    it('should not mark product streams as existing when none is found', async () => {
        const { wrapper } = await createWrapper([]);
        await flushPromises();

        expect(wrapper.find('.sw-sales-channel-modal-grid').attributes('data-streams-exist')).toBe('false');
        expect(wrapper.find('.sw-sales-channel-modal-grid').attributes('data-streams-loading')).toBe('false');
    });
});
