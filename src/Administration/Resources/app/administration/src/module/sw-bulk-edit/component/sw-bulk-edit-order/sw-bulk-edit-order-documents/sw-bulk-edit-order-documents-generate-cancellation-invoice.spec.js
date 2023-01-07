/**
 * @package system-settings
 */
import { shallowMount } from '@vue/test-utils';
import swBulkEditState from 'src/module/sw-bulk-edit/state/sw-bulk-edit.state';
import swBulkEditOrderDocumentsGenerateInvoice from 'src/module/sw-bulk-edit/component/sw-bulk-edit-order/sw-bulk-edit-order-documents-generate-invoice';
import swBulkEditOrderDocumentsGenerateCancellationInvoice from 'src/module/sw-bulk-edit/component/sw-bulk-edit-order/sw-bulk-edit-order-documents-generate-cancellation-invoice';

Shopware.Component.register('sw-bulk-edit-order-documents-generate-invoice', swBulkEditOrderDocumentsGenerateInvoice);
Shopware.Component.extend('sw-bulk-edit-order-documents-generate-cancellation-invoice', 'sw-bulk-edit-order-documents-generate-invoice', swBulkEditOrderDocumentsGenerateCancellationInvoice);

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-bulk-edit-order-documents-generate-cancellation-invoice'), {
        stubs: {
            'sw-datepicker': true,
            'sw-textarea-field': true,
        },
    });
}

describe('sw-bulk-edit-order-documents-generate-cancellation-invoice', () => {
    let wrapper;

    beforeAll(() => {
        Shopware.State.registerModule('swBulkEdit', swBulkEditState);
    });

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should contain a generateData as a computed property', async () => {
        expect(wrapper.vm.generateData).toEqual(expect.objectContaining({
            documentComment: null,
        }));

        Shopware.State.commit('swBulkEdit/setOrderDocumentsValue', {
            type: 'storno',
            value: {
                documentDate: 'documentDate',
                documentComment: 'documentComment',
            },
        });

        expect(wrapper.vm.generateData).toEqual(expect.objectContaining({
            documentDate: 'documentDate',
            documentComment: 'documentComment',
        }));
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
