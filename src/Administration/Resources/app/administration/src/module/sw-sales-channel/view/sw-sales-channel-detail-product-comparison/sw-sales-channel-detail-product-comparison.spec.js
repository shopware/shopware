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
                    'sw-button-process': {
                        template: '<button class="sw-button-process" :disabled="disabled"><slot></slot></button>',
                        props: ['disabled'],
                    },
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

    it('should have template actions disabled when the user has no privileges', async () => {
        const wrapper = await createWrapper({
            productExport: { bodyTemplate: 'product' },
        });

        const actions = wrapper.findAll('.sw-button-process');

        expect(actions).toHaveLength(2);
        actions.forEach((action) => {
            expect(action.attributes('disabled')).toBeDefined();
        });
    });

    it('should have template actions enabled when the user has privileges', async () => {
        global.activeAclRoles = ['sales_channel.editor'];

        const wrapper = await createWrapper({
            productExport: { bodyTemplate: 'product' },
        });

        const actions = wrapper.findAll('.sw-button-process');

        expect(actions).toHaveLength(2);
        actions.forEach((action) => {
            expect(action.attributes('disabled')).toBeUndefined();
        });
    });
});
