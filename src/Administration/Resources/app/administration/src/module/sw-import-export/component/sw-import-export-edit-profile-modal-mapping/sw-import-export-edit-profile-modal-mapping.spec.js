/**
 * @package services-settings
 */
import { shallowMount } from '@vue/test-utils';

import swImportExportEditProfileModalMapping from 'src/module/sw-import-export/component/sw-import-export-edit-profile-modal-mapping';
import 'src/app/component/base/sw-simple-search-field';
import 'src/app/component/base/sw-button';
import 'src/app/component/data-grid/sw-data-grid';
import 'src/app/component/form/sw-field';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-contextual-field';

Shopware.Component.register('sw-import-export-edit-profile-modal-mapping', swImportExportEditProfileModalMapping);

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
        return shallowMount(await Shopware.Component.build('sw-import-export-edit-profile-modal-mapping'), {
            propsData: {
                profile,
            },
            provide: {
                validationService: {},
            },
            stubs: {
                'sw-simple-search-field': await Shopware.Component.build('sw-simple-search-field'),
                'sw-button': await Shopware.Component.build('sw-button'),
                'sw-data-grid': await Shopware.Component.build('sw-data-grid'),
                'sw-import-export-entity-path-select': true,
                'sw-context-menu-item': true,
                'sw-context-button': true,
                'sw-field': await Shopware.Component.build('sw-field'),
                'sw-switch-field': true,
                'sw-text-field': await Shopware.Component.build('sw-text-field'),
                'sw-contextual-field': await Shopware.Component.build('sw-contextual-field'),
                'sw-block-field': await Shopware.Component.build('sw-block-field'),
                'sw-base-field': await Shopware.Component.build('sw-base-field'),
                'sw-button-group': {
                    template: '<div class="sw-button-group"><slot></slot></div>',
                },
                'sw-field-error': true,
                'sw-icon': true,
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

    afterEach(() => {
        if (wrapper) wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        wrapper = await createWrapper(getProfileMock());

        expect(wrapper.vm).toBeTruthy();
    });

    it('should sort mappings by their position', async () => {
        const mappingsInCorrectOrder = getDefaultMappingOrder();

        wrapper = await createWrapper(getProfileMock());

        const mappings = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');

        mappings.wrappers.forEach((currentWrapper, index) => {
            const key = currentWrapper.find('sw-import-export-entity-path-select-stub').attributes('value');

            expect(key).toBe(mappingsInCorrectOrder[index].key);
            expect(mappingsInCorrectOrder[index].position).toBe(index);
        });
    });

    it('should swap items downwards', async () => {
        const mappingsInCorrectOrder = getDefaultMappingOrder();

        wrapper = await createWrapper(getProfileMock());

        const mappings = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');

        mappings.wrappers.forEach((currentWrapper, index) => {
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

        const mappings = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');

        mappings.wrappers.forEach((currentWrapper, index) => {
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

        const upwardsButton = wrapper.find(selector);

        expect(upwardsButton.attributes('disabled')).toBe('disabled');
    });

    it('should add a mapping', async () => {
        const profileMock = getProfileMock();
        profileMock.systemDefault = false;

        wrapper = await createWrapper(profileMock);

        const amountBeforeCreation = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row').wrappers.length;
        expect(amountBeforeCreation).toBe(3);

        const addButton = wrapper.find('.sw-import-export-edit-profile-modal-mapping__add-action');
        await addButton.trigger('click');

        const amountAfterCreation = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row').wrappers.length;
        expect(amountAfterCreation).toBe(4);
    });

    it('should have a disabled up button on newly created button', async () => {
        const profileMock = getProfileMock();
        profileMock.systemDefault = false;

        wrapper = await createWrapper(profileMock);

        const addButton = wrapper.find('.sw-import-export-edit-profile-modal-mapping__add-action');
        await addButton.trigger('click');

        const firstMapping = wrapper.find('.sw-data-grid__row--0 .sw-button-group .sw-button:first-of-type');
        expect(firstMapping.attributes('disabled')).toBe('disabled');

        // check that the up button for the second mapping is not disabled
        const secondMapping = wrapper.find('.sw-data-grid__row--1 .sw-button-group .sw-button:first-of-type');
        expect(secondMapping.attributes('disabled')).toBeUndefined();
    });

    it('should have disabled buttons when searching', async () => {
        wrapper = await createWrapper(getProfileMock());

        const enabledPositionButtons = wrapper.findAll('.sw-data-grid__cell--position .sw-button:not([disabled])');

        expect(enabledPositionButtons.wrappers).toHaveLength(4);
        enabledPositionButtons.wrappers.forEach(button => {
            expect(button.attributes('disabled')).toBeUndefined();
        });

        await wrapper.setData({
            searchTerm: 'search term',
        });

        const disabledPositionButtons = wrapper.findAll('.sw-data-grid__cell--position .sw-button');

        expect(disabledPositionButtons.wrappers).toHaveLength(6);
        disabledPositionButtons.wrappers.forEach(button => {
            expect(button.attributes('disabled')).toBe('disabled');
        });
    });

    it('should always use the direct neighbour when swapping items', async () => {
        wrapper = await createWrapper(getProfileMock());

        const addButton = wrapper.find('.sw-import-export-edit-profile-modal-mapping__add-action');
        await addButton.trigger('click');

        const newItemCsvInput = wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--csvName input');
        await newItemCsvInput.setValue('custom_value');

        // assert structure
        const orderedItems = wrapper.findAll('.sw-data-grid__row .sw-data-grid__cell--csvName input');

        const expectedOrder = ['custom_value', 'id', 'parent_id', 'product_number'];
        const actualOrder = orderedItems.wrappers.map(input => {
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
