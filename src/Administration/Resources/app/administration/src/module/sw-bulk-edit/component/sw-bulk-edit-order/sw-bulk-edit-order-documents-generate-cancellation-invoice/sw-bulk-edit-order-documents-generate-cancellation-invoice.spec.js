/**
 * @sw-package checkout
 */
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-bulk-edit-order-documents-generate-cancellation-invoice', { sync: true }), {
        global: {
            stubs: {
                'sw-datepicker': true,
                'sw-textarea-field': true,
                'sw-switch-field': true,
            },
        },
    });
}

describe('sw-bulk-edit-order-documents-generate-cancellation-invoice', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should contain a generateData as a computed property', async () => {
        expect(wrapper.vm.generateData).toEqual(
            expect.objectContaining({
                documentComment: null,
            }),
        );

        Shopware.Store.get('swBulkEdit').setOrderDocumentsValue({
            type: 'storno',
            value: {
                documentDate: 'documentDate',
                documentComment: 'documentComment',
            },
        });

        expect(wrapper.vm.generateData).toEqual(
            expect.objectContaining({
                documentDate: 'documentDate',
                documentComment: 'documentComment',
            }),
        );
    });

    it('should be able to update generateData', async () => {
        wrapper.vm.generateData = {
            documentDate: 'I am a date',
            documentComment: 'I am a comment',
        };

        expect(wrapper.vm.generateData.documentDate).toBe('I am a date');
        expect(wrapper.vm.generateData.documentComment).toBe('I am a comment');
    });
});
