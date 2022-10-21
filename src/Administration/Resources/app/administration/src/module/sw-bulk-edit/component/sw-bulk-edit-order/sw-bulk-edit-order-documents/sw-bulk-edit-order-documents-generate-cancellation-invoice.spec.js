import { shallowMount } from '@vue/test-utils';
import swBulkEditState from 'src/module/sw-bulk-edit/state/sw-bulk-edit.state';
import 'src/module/sw-bulk-edit/component/sw-bulk-edit-order/sw-bulk-edit-order-documents-generate-invoice';
import 'src/module/sw-bulk-edit/component/sw-bulk-edit-order/sw-bulk-edit-order-documents-generate-cancellation-invoice';

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-bulk-edit-order-documents-generate-cancellation-invoice'), {
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

    beforeEach(() => {
        wrapper = createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should contain a generateData as a computed property', () => {
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

    it('should be able to update generateData', () => {
        wrapper.vm.generateData = {
            documentDate: 'I am a date',
            documentComment: 'I am a comment',
        };

        expect(wrapper.vm.generateData.documentDate).toBe('I am a date');
        expect(wrapper.vm.generateData.documentComment).toBe('I am a comment');
    });
});
