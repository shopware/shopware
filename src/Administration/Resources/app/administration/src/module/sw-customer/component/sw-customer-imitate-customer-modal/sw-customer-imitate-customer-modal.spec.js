import { mount } from '@vue/test-utils';

/**
 * @package checkout
 */

const responses = global.repositoryFactoryMock.responses;

responses.addResponse({
    method: 'Post',
    url: '/search/sales-channel-domain',
    status: 200,
    response: {
        data: [
            {
                id: '1',
            },
        ],
    },
});

const customer = {
    id: '1',
    email: null,
    boundSalesChannelId: null,
};

async function createWrapper() {
    return mount(await wrapTestComponent('sw-customer-imitate-customer-modal', { sync: true }), {
        props: {
            customer: customer,
        },
    });
}

describe('module/sw-customer-imitate-customer-modal', () => {
    let wrapper;

    it('should be a Vue.JS component', async () => {
        wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });
});
