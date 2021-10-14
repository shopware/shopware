import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-import-export/component/sw-import-export-edit-profile-modal-mapping';
import 'src/app/component/base/sw-simple-search-field';
import 'src/app/component/base/sw-button';
import 'src/app/component/data-grid/sw-data-grid';
import 'src/app/component/form/sw-field';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-contextual-field';

describe('module/sw-import-export/components/sw-import-export-edit-profile-modal-mapping', () => {
    let wrapper;

    function getDefaultMappingOrder() {
        return [
            {
                position: 0,
                key: 'id'
            },
            {
                position: 1,
                key: 'parentId'
            },
            {
                position: 2,
                key: 'productNumber'
            }
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
                    id: '0363d01e226846748f318eda91ab3450'
                },
                {
                    key: 'parentId',
                    mappedKey: 'parent_id',
                    position: 1,
                    id: 'a6388d0f7f7245ecaba4a4db6e683972'
                },
                {
                    key: 'productNumber',
                    mappedKey: 'product_number',
                    position: 2,
                    id: 'a4209aad611b4a51a32f69b9a2c693ff'
                }
            ]
        };
    }

    function createWrapper(profile) {
        return shallowMount(Shopware.Component.build('sw-import-export-edit-profile-modal-mapping'), {
            propsData: {
                profile
            },
            provide: {
                validationService: {}
            },
            stubs: {
                'sw-simple-search-field': Shopware.Component.build('sw-simple-search-field'),
                'sw-button': Shopware.Component.build('sw-button'),
                'sw-data-grid': Shopware.Component.build('sw-data-grid'),
                'sw-import-export-entity-path-select': true,
                'sw-context-menu-item': true,
                'sw-context-button': true,
                'sw-field': Shopware.Component.build('sw-field'),
                'sw-text-field': Shopware.Component.build('sw-text-field'),
                'sw-contextual-field': Shopware.Component.build('sw-contextual-field'),
                'sw-block-field': Shopware.Component.build('sw-block-field'),
                'sw-base-field': Shopware.Component.build('sw-base-field'),
                'sw-button-group': {
                    template: '<div class="sw-button-group"><slot></slot></div>'
                },
                'sw-field-error': true,
                'sw-icon': true
            }
        });
    }

    beforeEach(() => {
        const responses = global.repositoryFactoryMock.responses;
        global.activeFeatureFlags = ['FEATURE_NEXT_15998'];

        responses.addResponse({
            method: 'Post',
            url: '/search/language',
            status: 200,
            response: {
                data: []
            }
        });

        responses.addResponse({
            method: 'Post',
            url: '/search/currency',
            status: 200,
            response: {
                data: []
            }
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

        const reorderedMappings = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        const customMappingOrder = [
            {
                position: 0,
                key: 'parentId'
            },
            {
                position: 1,
                key: 'id'
            },
            {
                position: 2,
                key: 'productNumber'
            }
        ];

        reorderedMappings.wrappers.forEach((currentWrapper, index) => {
            const key = currentWrapper.find('sw-import-export-entity-path-select-stub').attributes('value');

            expect(key).toBe(customMappingOrder[index].key);
            expect(customMappingOrder[index].position).toBe(index);
        });
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

        const reorderedMappings = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        const customMappingOrder = [
            {
                position: 0,
                key: 'id'
            },
            {
                position: 1,
                key: 'productNumber'
            },
            {
                position: 2,
                key: 'parentId'
            }
        ];

        reorderedMappings.wrappers.forEach((currentWrapper, index) => {
            const key = currentWrapper.find('sw-import-export-entity-path-select-stub').attributes('value');

            expect(key).toBe(customMappingOrder[index].key);
            expect(customMappingOrder[index].position).toBe(index);
        });
    });

    it.each([
        ['.sw-data-grid__row--0 .sw-button-group .sw-button:first-of-type'],
        ['.sw-data-grid__row--2 .sw-button-group .sw-button:last-of-type']
    ])('should have a first disabled button', (selector) => {
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

    it('should have disabled sorting buttons while using the search', async () => {
        wrapper = await createWrapper(getProfileMock());

        await wrapper.setData({
            searchTerm: 'productNumber'
        });

        wrapper.vm.loadMappings();

        await wrapper.vm.$nextTick();

        const amountWhileUsingSearch = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row').wrappers.length;
        expect(amountWhileUsingSearch).toBe(1);
    });

    it('should always use the direct neighbour when swapping positions', async () => {
        const profileMock = getProfileMock();
        profileMock.mapping.pop();
        profileMock.systemDefault = false;

        wrapper = await createWrapper(profileMock);

        const firstRowDownwardsButton = wrapper.find('.sw-data-grid__row--0 .sw-button-group .sw-button:last-of-type');
        await firstRowDownwardsButton.trigger('click');

        const addButton = wrapper.find('.sw-import-export-edit-profile-modal-mapping__add-action');
        await addButton.trigger('click');

        const newItemInputField = wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--csvName input');
        await newItemInputField.setValue('custom_value');

        const newItemDownwardsButton = wrapper.find('.sw-data-grid__row--0 .sw-button-group .sw-button:last-of-type');
        await newItemDownwardsButton.trigger('click');

        const reorderedMappings = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        const customMappingOrder = [
            {
                position: 0,
                mappedKey: 'parent_id'
            },
            {
                position: 1,
                mappedKey: 'custom_value'
            },
            {
                position: 2,
                mappedKey: 'id'
            }
        ];

        reorderedMappings.wrappers.forEach((currentWrapper, index) => {
            const key = currentWrapper.find('input[type="text"]').element.value;

            expect(key).toBe(customMappingOrder[index].mappedKey);
            expect(customMappingOrder[index].position).toBe(index);
        });
    });
});
