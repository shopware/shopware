import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-sales-channel/view/sw-sales-channel-detail-product-comparison';

function createWrapper(privileges = []) {
    return shallowMount(Shopware.Component.build('sw-sales-channel-detail-product-comparison'), {
        stubs: {
            'sw-card': true,
            'sw-code-editor': true,
            'sw-container': true,
            'sw-button-process': true,
            'sw-sales-channel-detail-product-comparison-preview': true
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
                }
            }
        },
        mocks: {
            $tc: v => v
        },
        propsData: {
            productExport: {},
            salesChannel: {}
        }
    });
}

describe('src/module/sw-sales-channel/view/sw-sales-channel-detail-product-comparison', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should have codeEditors disabled when the user has no privileges', async () => {
        const wrapper = createWrapper();

        const codeEditors = wrapper.findAll('sw-code-editor-stub');

        codeEditors.wrappers.forEach(codeEditor => {
            expect(codeEditor.attributes().disabled).toBe('true');
        });
    });

    it('should have codeEditors enabled when the user has privileges', async () => {
        const wrapper = createWrapper([
            'sales_channel.editor'
        ]);

        const codeEditors = wrapper.findAll('sw-field-stub');

        codeEditors.wrappers.forEach(codeEditor => {
            expect(codeEditor.attributes().disabled).toBeUndefined();
        });
    });
});
