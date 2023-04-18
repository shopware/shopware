/**
 * @package system-settings
 */
import { shallowMount } from '@vue/test-utils';

import swImportExportEditProfileModalIdentifiers from 'src/module/sw-import-export/component/sw-import-export-edit-profile-modal-identifiers';
import 'src/app/component/data-grid/sw-data-grid';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/select/base/sw-single-select';
import 'src/app/component/form/select/base/sw-select-result-list';
import 'src/app/component/form/select/base/sw-select-result';
import 'src/app/component/utils/sw-popover';
import ImportExportUpdateByMappingService from 'src/module/sw-import-export/service/importExportUpdateByMapping.service';
// eslint-disable-next-line import/no-unresolved
import entitySchemaMock from 'src/../test/_mocks_/entity-schema.json';

Shopware.Component.register('sw-import-export-edit-profile-modal-identifiers', swImportExportEditProfileModalIdentifiers);

describe('module/sw-import-export/components/sw-import-export-edit-profile-modal-identifiers', () => {
    let wrapper;

    function getProfileMock() {
        return {
            sourceEntity: 'product',
            systemDefault: false,
            mapping: [
                {
                    key: 'productNumber',
                    mappedKey: 'product_number',
                    position: 0,
                },
                {
                    key: 'manufacturer.translations.DEFAULT.name',
                    mappedKey: 'manufacturer_name',
                    position: 1,
                },
                {
                    key: 'properties',
                    mappedKey: 'properties',
                    position: 2,
                },
                {
                    key: 'tax.id',
                    mappedKey: 'tax_id',
                    position: 3,
                },
                {
                    key: 'tax.taxRate',
                    mappedKey: 'tax_rate',
                    position: 4,
                },
            ],
            updateBy: [
                {
                    mappedKey: 'translations.DEFAULT.name',
                    entityName: 'product_manufacturer',
                },
            ],
        };
    }

    async function createWrapper(profile) {
        Object.entries(entitySchemaMock).forEach(([entityName, entityDefinition]) => {
            Shopware.EntityDefinition.add(entityName, entityDefinition);
        });

        return shallowMount(await Shopware.Component.build('sw-import-export-edit-profile-modal-identifiers'), {
            propsData: {
                profile,
            },
            provide: {
                importExportUpdateByMapping: new ImportExportUpdateByMappingService(Shopware.EntityDefinition),
            },
            stubs: {
                'sw-data-grid': await Shopware.Component.build('sw-data-grid'),
                'sw-import-export-entity-path-select': true,
                'sw-base-field': await Shopware.Component.build('sw-base-field'),
                'sw-block-field': await Shopware.Component.build('sw-block-field'),
                'sw-select-base': await Shopware.Component.build('sw-select-base'),
                'sw-single-select': await Shopware.Component.build('sw-single-select'),
                'sw-empty-state': true,
                'sw-icon': true,
                'sw-field-error': true,
                'sw-select-result-list': await Shopware.Component.build('sw-select-result-list'),
                'sw-popover': await Shopware.Component.build('sw-popover'),
                'sw-select-result': await Shopware.Component.build('sw-select-result'),
                'sw-highlight-text': {
                    props: ['text'],
                    template: '<div class="sw-highlight-text">{{ this.text }}</div>',
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

    afterEach(() => {
        if (wrapper) wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        wrapper = await createWrapper(getProfileMock());

        expect(wrapper.vm).toBeTruthy();
    });

    it('should have identifier entries for all entities in mapping', async () => {
        const profileMock = getProfileMock();

        wrapper = await createWrapper(profileMock);

        expect(wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--identifierName').text()).toBe('product');
        expect(wrapper.find('.sw-data-grid__row--1 .sw-data-grid__cell--identifierName').text()).toBe('manufacturer');
        expect(wrapper.find('.sw-data-grid__row--2 .sw-data-grid__cell--identifierName').text()).toBe('properties');
        expect(wrapper.find('.sw-data-grid__row--3 .sw-data-grid__cell--identifierName').text()).toBe('tax');
    });

    it('should have options for entries in update by mapping', async () => {
        const profileMock = getProfileMock();

        wrapper = await createWrapper(profileMock);

        await wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--mapped .sw-select__selection').trigger('click');
        await wrapper.vm.$nextTick();
        expect(wrapper.find('.sw-select-result-list__item-list .sw-select-option--productNumber').exists()).toBeTruthy();

        expect(wrapper.find('.sw-data-grid__row--1 .sw-data-grid__cell--mapped .sw-single-select__selection-text').text()).toBe('translations.DEFAULT.name');

        expect(wrapper.find('.sw-data-grid__row--2 .sw-data-grid__cell--mapped sw-import-export-entity-path-select-stub').exists()).toBeTruthy();

        expect(wrapper.find('.sw-data-grid__row--3 .sw-data-grid__cell--mapped .sw-single-select__selection-text').text()).toBe('id');
        await wrapper.find('.sw-data-grid__row--3 .sw-data-grid__cell--mapped .sw-select__selection').trigger('click');
        await wrapper.vm.$nextTick();
        expect(wrapper.find('.sw-select-result-list__item-list .sw-select-option--id').exists()).toBeTruthy();
        expect(wrapper.find('.sw-select-result-list__item-list .sw-select-option--taxRate').exists()).toBeTruthy();
    });
});
