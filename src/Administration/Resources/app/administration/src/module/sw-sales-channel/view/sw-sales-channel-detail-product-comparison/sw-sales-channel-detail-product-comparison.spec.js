/**
 * @package buyers-experience
 */

import { shallowMount } from '@vue/test-utils';
import swSalesChannelDetailProductComparison from 'src/module/sw-sales-channel/view/sw-sales-channel-detail-product-comparison';

Shopware.Component.register('sw-sales-channel-detail-product-comparison', swSalesChannelDetailProductComparison);

async function createWrapper(privileges = []) {
    return shallowMount(await Shopware.Component.build('sw-sales-channel-detail-product-comparison'), {
        stubs: {
            'sw-card': true,
            'sw-code-editor': true,
            'sw-container': true,
            'sw-button-process': true,
            'sw-sales-channel-detail-product-comparison-preview': true,
        },
        provide: {
            salesChannelService: {},
            productExportService: {},
            entityMappingService: {},
            repositoryFactory: {},
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                },
            },
        },
        propsData: {
            productExport: {},
            salesChannel: {},
        },
    });
}

describe('src/module/sw-sales-channel/view/sw-sales-channel-detail-product-comparison', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should have codeEditors disabled when the user has no privileges', async () => {
        const wrapper = await createWrapper();

        const codeEditors = wrapper.findAll('sw-code-editor-stub');

        codeEditors.wrappers.forEach(codeEditor => {
            expect(codeEditor.attributes().disabled).toBe('true');
        });
    });

    it('should have codeEditors enabled when the user has privileges', async () => {
        const wrapper = await createWrapper([
            'sales_channel.editor',
        ]);

        const codeEditors = wrapper.findAll('sw-field-stub');

        codeEditors.wrappers.forEach(codeEditor => {
            expect(codeEditor.attributes().disabled).toBeUndefined();
        });
    });
});
