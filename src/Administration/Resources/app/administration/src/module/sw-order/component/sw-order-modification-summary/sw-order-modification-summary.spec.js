import { mount } from '@vue/test-utils';
import 'src/app/filter/currency.filter';

/**
 * @sw-package checkout
 */

const createEndpoint = jest.fn(() => ({ isNew: () => true, id: 'new-modification-id' }));
const saveEndpoint = jest.fn(() => Promise.resolve());
const deleteEndpoint = jest.fn(() => Promise.resolve());

async function createWrapper(overrides = {}) {
    return mount(await wrapTestComponent('sw-order-modification-summary', { sync: true }), {
        global: {
            stubs: {
                'sw-order-modification-modal': true,
                'sw-modal': {
                    template: '<div class="sw-modal"><slot></slot><slot name="modal-footer"></slot></div>',
                },
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        create: createEndpoint,
                        save: saveEndpoint,
                        delete: deleteEndpoint,
                    }),
                },
            },
        },
        props: {
            order: {
                id: 'order-id',
                currency: { isoCode: 'EUR' },
                totalRounding: { decimals: 2 },
                priceModifications: [],
                ...overrides.order,
            },
            context: {},
            editable: overrides.editable ?? true,
        },
    });
}

describe('src/module/sw-order/component/sw-order-modification-summary', () => {
    beforeEach(() => {
        createEndpoint.mockClear();
        saveEndpoint.mockClear();
        deleteEndpoint.mockClear();
    });

    it('identifies a percentage-type modification', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.isPercentageType({ priceDefinition: { type: 'percentage' } })).toBe(true);
        expect(wrapper.vm.isPercentageType({ priceDefinition: { type: 'absolute' } })).toBe(false);
        expect(wrapper.vm.isPercentageType({ priceDefinition: null })).toBe(false);
    });

    it('hides edit and delete actions for a percentage-type row even when editable', async () => {
        const wrapper = await createWrapper({
            order: {
                priceModifications: [{ id: 'mod-1', label: 'Fee', price: 5, priceDefinition: { type: 'percentage' } }],
            },
        });

        expect(wrapper.find('.sw-order-modification-summary__edit-action').exists()).toBe(false);
        expect(wrapper.find('.sw-order-modification-summary__delete-action').exists()).toBe(false);
    });

    it('shows edit and delete actions for an editable, non-percentage row', async () => {
        const wrapper = await createWrapper({
            order: {
                priceModifications: [{ id: 'mod-1', label: 'Fee', price: 5, priceDefinition: { type: 'absolute' } }],
            },
        });

        expect(wrapper.find('.sw-order-modification-summary__edit-action').exists()).toBe(true);
        expect(wrapper.find('.sw-order-modification-summary__delete-action').exists()).toBe(true);
    });

    it('hides every row action when not editable', async () => {
        const wrapper = await createWrapper({
            editable: false,
            order: {
                priceModifications: [{ id: 'mod-1', label: 'Fee', price: 5, priceDefinition: { type: 'absolute' } }],
            },
        });

        expect(wrapper.find('.sw-order-modification-summary__edit-action').exists()).toBe(false);
        expect(wrapper.find('.sw-order-modification-summary__delete-action').exists()).toBe(false);
    });

    it('creates a new modification via the repository on add', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.onAddModification();

        expect(createEndpoint).toHaveBeenCalled();
        expect(wrapper.vm.showFormFor).not.toBeNull();
        expect(wrapper.vm.showFormFor.price).toBe(0);
        expect(wrapper.vm.showFormFor.taxRules).toEqual([]);
    });

    it('saves via the repository and requests a recalculate on save', async () => {
        const wrapper = await createWrapper();
        const modification = { id: 'mod-1' };
        wrapper.vm.showFormFor = modification;

        wrapper.vm.onSaveModification(modification);
        await flushPromises();

        expect(saveEndpoint).toHaveBeenCalledWith(modification, expect.anything());
        expect(wrapper.vm.showFormFor).toBeNull();
        expect(wrapper.emitted('save-and-recalculate')).toBeTruthy();
    });

    it('requires confirmation before deleting', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.onDeleteRequest({ id: 'mod-1' });

        expect(wrapper.vm.showDeleteModal).toBe('mod-1');
        expect(deleteEndpoint).not.toHaveBeenCalled();
    });

    it('deletes via the repository and requests a recalculate-and-reload on confirm', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.onDeleteRequest({ id: 'mod-1' });

        wrapper.vm.onConfirmDelete();
        await flushPromises();

        expect(deleteEndpoint).toHaveBeenCalledWith('mod-1', expect.anything());
        expect(wrapper.vm.showDeleteModal).toBeNull();
        expect(wrapper.emitted('recalculate-and-reload')).toBeTruthy();
    });
});
