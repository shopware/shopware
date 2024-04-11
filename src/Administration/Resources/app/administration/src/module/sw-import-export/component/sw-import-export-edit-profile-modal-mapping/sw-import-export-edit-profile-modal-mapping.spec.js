/**
 * @package services-settings
 */
import { mount } from '@vue/test-utils';

describe('module/sw-import-export/components/sw-import-export-edit-profile-modal-mapping', () => {
    let wrapper;

    function getDefaultMappingOrder() {
        return [
            {
                position: 0,
                key: 'id',
            },
            {
                position: 1,
                key: 'parentId',
            },
            {
                position: 2,
                key: 'productNumber',
            },
        ];
    }

    function getProfileMock() {
        return {
            sourceEntity: 'product',
            mapping: [
                {
                    key: 'id',
                    mappedKey: 'id',
                    position: 0,
                    id: '0363d01e226846748f318eda91ab3450',
                },
                {
                    key: 'parentId',
                    mappedKey: 'parent_id',
                    position: 1,
                    id: 'a6388d0f7f7245ecaba4a4db6e683972',
                },
                {
                    key: 'productNumber',
                    mappedKey: 'product_number',
                    position: 2,
                    id: 'a4209aad611b4a51a32f69b9a2c693ff',
                },
            ],
        };
    }

    async function createWrapper(profile) {
        return mount(await wrapTestComponent('sw-import-export-edit-profile-modal-mapping', {
            sync: true,
        }), {
            props: {
                profile,
            },
            global: {
                provide: {
                    validationService: {},
                },
                stubs: {
                    'sw-simple-search-field': await wrapTestComponent('sw-simple-search-field'),
                    'sw-button': await wrapTestComponent('sw-button'),
                    'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated'),
                    'sw-data-grid': await wrapTestComponent('sw-data-grid'),
                    'sw-import-export-entity-path-select': true,
                    'sw-context-menu-item': true,
                    'sw-context-button': true,
                    'sw-switch-field': true,
                    'sw-text-field': await wrapTestComponent('sw-text-field'),
                    'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                    'sw-block-field': await wrapTestComponent('sw-block-field'),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-button-group': {
                        template: '<div class="sw-button-group"><slot></slot></div>',
                    },
                    'sw-field-error': true,
                    'sw-icon': true,
                },
            },
        });
    }

    beforeEach(async () => {
        const responses = global.repositoryFactoryMock.responses;

        responses.addResponse({
            method: 'Post',
            url: '/search/language',
            status: 200,
            response: {
                data: [],
            },
        });

        responses.addResponse({
            method: 'Post',
            url: '/search/currency',
            status: 200,
            response: {
                data: [],
            },
        });

        responses.addResponse({
            method: 'Post',
            url: '/search/custom-field-set',
            status: 200,
            response: {
                data: [],
            },
        });
    });

    it('should be a Vue.js component', async () => {
        wrapper = await createWrapper(getProfileMock());
        await flushPromises();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should sort mappings by their position', async () => {
        const mappingsInCorrectOrder = getDefaultMappingOrder();

        wrapper = await createWrapper(getProfileMock());
        await flushPromises();

        const mappings = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');

        mappings.forEach((currentWrapper, index) => {
            const key = currentWrapper.find('sw-import-export-entity-path-select-stub').attributes('value');

            expect(key).toBe(mappingsInCorrectOrder[index].key);
            expect(mappingsInCorrectOrder[index].position).toBe(index);
        });
    });

    it('should swap items downwards', async () => {
        const mappingsInCorrectOrder = getDefaultMappingOrder();

        wrapper = await createWrapper(getProfileMock());
        await flushPromises();

        const mappings = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');

        mappings.forEach((currentWrapper, index) => {
            const key = currentWrapper.find('sw-import-export-entity-path-select-stub').attributes('value');

            expect(key).toBe(mappingsInCorrectOrder[index].key);
            expect(mappingsInCorrectOrder[index].position).toBe(index);
        });

        const downwardsButton = wrapper.find('.sw-data-grid__row--0 .sw-button-group .sw-button:last-of-type');

        await downwardsButton.trigger('click');

        const customMappingOrder = [
            {
                position: 1,
                key: 'id',
                mappedKey: 'id',
                id: '0363d01e226846748f318eda91ab3450',
            },
            {
                position: 0,
                key: 'parentId',
                mappedKey: 'parent_id',
                id: 'a6388d0f7f7245ecaba4a4db6e683972',
            },
            {
                position: 2,
                mappedKey: 'product_number',
                key: 'productNumber',
                id: 'a4209aad611b4a51a32f69b9a2c693ff',
            },
        ];


        const emittedMappings = wrapper.emitted('update-mapping')[0][0];

        expect(emittedMappings).toEqual(customMappingOrder);
    });

    it('should swap items upwards', async () => {
        const mappingsInCorrectOrder = getDefaultMappingOrder();

        wrapper = await createWrapper(getProfileMock());
        await flushPromises();

        const mappings = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');

        mappings.forEach((currentWrapper, index) => {
            const key = currentWrapper.find('sw-import-export-entity-path-select-stub').attributes('value');

            expect(key).toBe(mappingsInCorrectOrder[index].key);
            expect(mappingsInCorrectOrder[index].position).toBe(index);
        });

        const downwardsButton = wrapper.find('.sw-data-grid__row--2 .sw-button-group .sw-button:first-of-type');

        await downwardsButton.trigger('click');

        const customMappingOrder = [
            {
                id: '0363d01e226846748f318eda91ab3450',
                position: 0,
                key: 'id',
                mappedKey: 'id',
            },
            {
                id: 'a6388d0f7f7245ecaba4a4db6e683972',
                position: 2,
                key: 'parentId',
                mappedKey: 'parent_id',
            },
            {
                position: 1,
                id: 'a4209aad611b4a51a32f69b9a2c693ff',
                key: 'productNumber',
                mappedKey: 'product_number',
            },
        ];

        const emittedMappings = wrapper.emitted('update-mapping')[0][0];

        expect(emittedMappings).toEqual(customMappingOrder);
    });

    it.each([
        ['.sw-data-grid__row--0 .sw-button-group .sw-button:first-of-type'],
        ['.sw-data-grid__row--2 .sw-button-group .sw-button:last-of-type'],
    ])('should have a first disabled button', async (selector) => {
        const profileMock = getProfileMock();
        profileMock.systemDefault = false;

        wrapper = await createWrapper(profileMock);
        await flushPromises();

        const upwardsButton = wrapper.find(selector);

        expect(upwardsButton.classes()).toContain('sw-button--disabled');
    });

    it('should add a mapping', async () => {
        const profileMock = getProfileMock();
        profileMock.systemDefault = false;

        wrapper = await createWrapper(profileMock);
        await flushPromises();

        const amountBeforeCreation = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row').length;
        expect(amountBeforeCreation).toBe(3);

        const addButton = wrapper.find('.sw-import-export-edit-profile-modal-mapping__add-action');
        await addButton.trigger('click');
        await flushPromises();

        const amountAfterCreation = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row').length;
        expect(amountAfterCreation).toBe(4);
    });

    it('should have a disabled up button on newly created button', async () => {
        const profileMock = getProfileMock();
        profileMock.systemDefault = false;

        wrapper = await createWrapper(profileMock);
        await flushPromises();

        const addButton = wrapper.find('.sw-import-export-edit-profile-modal-mapping__add-action');
        await addButton.trigger('click');

        const firstMapping = wrapper.find('.sw-data-grid__row--0 .sw-button-group .sw-button:first-of-type');
        expect(firstMapping.classes()).toContain('sw-button--disabled');

        // check that the up button for the second mapping is not disabled
        const secondMapping = wrapper.find('.sw-data-grid__row--1 .sw-button-group .sw-button:first-of-type');
        expect(secondMapping.attributes('disabled')).toBeUndefined();
        expect(secondMapping.classes()).not.toContain('sw-button--disabled');
    });

    it('should have disabled buttons when searching', async () => {
        wrapper = await createWrapper(getProfileMock());
        await flushPromises();

        const enabledPositionButtons = wrapper.findAll('.sw-data-grid__cell--position .sw-button:not([disabled])');

        expect(enabledPositionButtons).toHaveLength(4);
        enabledPositionButtons.forEach(button => {
            expect(button.attributes('disabled')).toBeUndefined();
        });

        await wrapper.setData({
            searchTerm: 'search term',
        });

        const disabledPositionButtons = wrapper.findAll('.sw-data-grid__cell--position .sw-button');

        expect(disabledPositionButtons).toHaveLength(6);
        disabledPositionButtons.forEach(button => {
            expect(button.classes()).toContain('sw-button--disabled');
        });
    });

    it('should always use the direct neighbour when swapping items', async () => {
        wrapper = await createWrapper(getProfileMock());
        await flushPromises();

        const addButton = wrapper.find('.sw-import-export-edit-profile-modal-mapping__add-action');
        await addButton.trigger('click');
        await flushPromises();

        const newItemCsvInput = wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--csvName input');
        await newItemCsvInput.setValue('custom_value');
        await flushPromises();

        // assert structure
        const orderedItems = wrapper.findAll('.sw-data-grid__row .sw-data-grid__cell--csvName input');

        const expectedOrder = ['custom_value', 'id', 'parent_id', 'product_number'];
        const actualOrder = orderedItems.map(input => {
            return input.element.value;
        });

        expect(actualOrder).toEqual(expectedOrder);

        const downwardsButton = wrapper
            .find('.sw-data-grid__row--0 .sw-data-grid__cell--position button:not([disabled])');

        await downwardsButton.trigger('click');

        // assert event
        const reorderedEvent = wrapper.emitted('update-mapping')[0][0];

        // removing unnecessary data
        const actualEventData = reorderedEvent.map(mapping => {
            delete mapping.id;

            return mapping;
        });

        const expectedEventData = [
            {
                key: '',
                mappedKey: 'custom_value',
                position: 1,
            },
            {
                key: 'id',
                mappedKey: 'id',
                position: 0,
            },
            {
                key: 'parentId',
                mappedKey: 'parent_id',
                position: 2,
            },
            {
                key: 'productNumber',
                mappedKey: 'product_number',
                position: 3,
            },
        ];

        expect(actualEventData).toEqual(expectedEventData);
    });
});
