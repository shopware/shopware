/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

const snippetPrefix = 'sw-bulk-edit.modal.confirm.triggeredFlows';

async function createWrapper(bulkEditData, translate = (path) => path) {
    return mount(
        await wrapTestComponent('sw-bulk-edit-save-modal-confirm', {
            sync: true,
        }),
        {
            props: {
                itemTotal: 2,
                bulkEditData,
            },
            global: {
                mocks: {
                    $t: translate,
                },
                stubs: {
                    'mt-switch': {
                        template: '<div class="mt-switch"></div>',
                    },
                    'mt-banner': {
                        template: '<div class="mt-banner"><slot /></div>',
                    },
                },
            },
        },
    );
}

describe('src/module/sw-bulk-edit/component/sw-bulk-edit-save-modal-confirm', () => {
    it('lists the flows of every changed flow-triggering field', async () => {
        const wrapper = await createWrapper({
            orderDeliveries: { isChanged: true },
            orderTransactions: { isChanged: true },
            orders: { isChanged: true },
            requestedCustomerGroupId: { isChanged: true },
        });

        expect(wrapper.find('.sw-bulk-edit-save-modal-confirm__trigger-flows-alert span').text()).toBe(
            [
                `${snippetPrefix}.orderDeliveries.label`,
                `${snippetPrefix}.orderTransactions.label`,
                `${snippetPrefix}.orders.label`,
                `${snippetPrefix}.requestedCustomerGroupId.label`,
            ].join(', '),
        );
        expect(wrapper.find('.sw-bulk-edit-save-modal-confirm__trigger-flows-switch').exists()).toBe(true);
    });

    it('ignores translated legacy key snippets when matching flow-triggering fields', async () => {
        const translatedSnippets = {
            [`${snippetPrefix}.orders.key`]: 'zamówienia',
            [`${snippetPrefix}.orders.label`]: 'Zmiany statusu zamówienia',
        };
        const wrapper = await createWrapper({ orders: { isChanged: true } }, (path) => translatedSnippets[path] ?? path);

        expect(wrapper.find('.sw-bulk-edit-save-modal-confirm__trigger-flows-alert span').text()).toBe(
            'Zmiany statusu zamówienia',
        );
        expect(wrapper.find('.sw-bulk-edit-save-modal-confirm__trigger-flows-switch').exists()).toBe(true);
    });

    it('ignores unchanged and non flow-triggering fields', async () => {
        const wrapper = await createWrapper({
            orders: { isChanged: false },
            customFields: { isChanged: true },
            statusMails: null,
        });

        expect(wrapper.find('.sw-bulk-edit-save-modal-confirm__trigger-flows').exists()).toBe(false);
    });
});
