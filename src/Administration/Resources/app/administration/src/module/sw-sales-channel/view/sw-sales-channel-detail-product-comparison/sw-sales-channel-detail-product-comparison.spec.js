/**
 * @sw-package discovery
 */

import { mount } from '@vue/test-utils';

async function createWrapper(props = {}) {
    return mount(
        await wrapTestComponent('sw-sales-channel-detail-product-comparison', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'mt-card': {
                        template: '<div class="mt-card"><slot></slot></div>',
                    },
                    'sw-code-editor': {
                        template: '<div class="sw-code-editor"></div>',
                        props: ['disabled'],
                    },
                    'sw-container': {
                        template: '<div class="sw-container"><slot></slot></div>',
                    },
                    'sw-button-process': true,
                    'sw-sales-channel-detail-product-comparison-preview': true,
                },
                provide: {
                    salesChannelService: {},
                    productExportService: {},
                    entityMappingService: {},
                    repositoryFactory: {},
                },
            },
            props: {
                productExport: {},
                salesChannel: {},
                ...props,
            },
        },
    );
}

describe('src/module/sw-sales-channel/view/sw-sales-channel-detail-product-comparison', () => {
    beforeEach(() => {
        global.activeAclRoles = [];
    });

    it('should have codeEditors disabled when the user has no privileges', async () => {
        const wrapper = await createWrapper();

        const codeEditors = wrapper.findAllComponents('.sw-code-editor');

        expect(codeEditors.length).toBeGreaterThan(0);
        codeEditors.forEach((codeEditor) => {
            expect(codeEditor.props('disabled')).toBe(true);
        });
    });

    it('should have codeEditors enabled when the user has privileges', async () => {
        global.activeAclRoles = [
            'sales_channel.editor',
        ];

        const wrapper = await createWrapper();

        const codeEditors = wrapper.findAllComponents('.sw-code-editor');

        expect(codeEditors.length).toBeGreaterThan(0);
        codeEditors.forEach((codeEditor) => {
            expect(codeEditor.attributes().disabled).toBeUndefined();
        });
    });

    it('binds the feed label input to productExport.feedLabel', async () => {
        global.activeAclRoles = [
            'sales_channel.editor',
        ];

        const wrapper = await createWrapper({
            productExport: { feedLabel: '' },
        });

        const input = wrapper.find('.sw-sales-channel-detail-product-comparison__feed-label input');
        expect(input.exists()).toBe(true);

        await input.setValue('SUMMER-2026');
        expect(wrapper.vm.productExport.feedLabel).toBe('SUMMER-2026');
    });

    it('passes empty string to mt-text-field when feedLabel is null so the counter does not read String(null).length', async () => {
        global.activeAclRoles = [
            'sales_channel.editor',
        ];

        const wrapper = await createWrapper({
            productExport: { feedLabel: null },
        });

        const field = wrapper.findComponent('.sw-sales-channel-detail-product-comparison__feed-label');
        expect(field.props('modelValue')).toBe('');
    });

    it('writes null back to feedLabel when the input is cleared', async () => {
        global.activeAclRoles = [
            'sales_channel.editor',
        ];

        const wrapper = await createWrapper({
            productExport: { feedLabel: 'SUMMER-2026' },
        });

        const input = wrapper.find('.sw-sales-channel-detail-product-comparison__feed-label input');
        await input.setValue('');

        expect(wrapper.vm.productExport.feedLabel).toBeNull();
    });
});
