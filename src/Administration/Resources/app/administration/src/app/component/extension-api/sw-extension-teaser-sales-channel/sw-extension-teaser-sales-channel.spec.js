/**
 * @package admin
 */

import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-extension-teaser-sales-channel', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'mt-icon': true,
                    'sw-extension-teaser-popover': true,
                },
            },
        },
    );
}

describe('src/app/component/extension-api/sw-extension-teaser-sales-channel', () => {
    let wrapper = null;
    let store = null;

    beforeEach(async () => {
        store = Shopware.Store.get('teaserPopover');
        store.salesChannels = [];
    });

    it('should be a Vue.js component', async () => {
        wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render correctly', async () => {
        store.addSalesChannel({
            positionId: 'positionId',
            salesChannel: {
                title: 'Facebook',
                description: 'Sell products on Facebook',
                iconName: 'facebook',
            },
            popoverComponent: {
                src: 'http://localhost:8080',
                component: 'button',
                props: {
                    locationId: 'locationId',
                    label: 'Ask AI Copilot',
                },
            },
        });

        wrapper = await createWrapper();
        const salesChannels = wrapper.findAll('.sw-extension-teaser-sales-channel');

        expect(salesChannels).toHaveLength(1);

        const salesChannel = salesChannels[0];
        expect(salesChannel.find('mt-icon-stub').attributes('name')).toBe('facebook');
        expect(salesChannel.find('.sw-extension-teaser-sales-channel__item-name').text()).toBe('Facebook');
        expect(salesChannel.find('.sw-extension-teaser-sales-channel__item-description').text()).toBe(
            'Sell products on Facebook',
        );
    });
});
