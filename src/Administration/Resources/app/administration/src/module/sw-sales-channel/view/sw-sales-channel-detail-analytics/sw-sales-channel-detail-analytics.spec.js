/**
 * @package buyers-experience
 */

import { shallowMount } from '@vue/test-utils';
import swSalesChannelDetailAnalytics from 'src/module/sw-sales-channel/view/sw-sales-channel-detail-analytics';

Shopware.Component.register('sw-sales-channel-detail-analytics', swSalesChannelDetailAnalytics);

async function createWrapper(privileges = []) {
    return shallowMount(await Shopware.Component.build('sw-sales-channel-detail-analytics'), {
        stubs: {
            'sw-card': true,
            'sw-switch-field': true,
            'sw-text-field': true,
            'sw-container': true,
        },
        provide: {
            repositoryFactory: {
                create: () => ({
                    create: () => ({}),
                }),
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                },
            },
        },
        propsData: {
            salesChannel: {},
        },
    });
}

/**
 * @package merchant-services
 */
describe('src/module/sw-sales-channel/view/sw-sales-channel-detail-analytics', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should have fields disabled when the user has no privileges', async () => {
        const wrapper = await createWrapper();

        const fields = wrapper.findAll('sw-field-stub');

        fields.wrappers.forEach(field => {
            expect(field.attributes().disabled).toBe('true');
        });
    });

    it('should have fields enabled when the user has privileges', async () => {
        const wrapper = await createWrapper([
            'sales_channel.editor',
        ]);

        const fields = wrapper.findAll('sw-field-stub');

        fields.wrappers.forEach(field => {
            expect(field.attributes().disabled).toBeUndefined();
        });
    });
});
