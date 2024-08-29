/**
 * @package services-settings
 * @group disabledCompat
 */
import { mount } from '@vue/test-utils';
import swBulkEditState from 'src/module/sw-bulk-edit/state/sw-bulk-edit.state';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-bulk-edit-order-documents-generate-delivery-note', { sync: true }), {
        global: {
            stubs: {
                'sw-datepicker': true,
                'sw-textarea-field': true,
            },
        },
    });
}

describe('sw-bulk-edit-order-documents-generate-delivery-note', () => {
    let wrapper;

    beforeAll(() => {
        Shopware.State.registerModule('swBulkEdit', swBulkEditState);
    });

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should contain a generateData as a computed property', async () => {
        expect(wrapper.vm.generateData).toEqual(expect.objectContaining({
            documentComment: null,
        }));

        Shopware.State.commit('swBulkEdit/setOrderDocumentsValue', {
            type: 'delivery_note',
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
