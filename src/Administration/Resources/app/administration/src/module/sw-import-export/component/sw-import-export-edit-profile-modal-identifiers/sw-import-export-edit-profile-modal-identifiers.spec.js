/**
 * @package services-settings
 * @group disabledCompat
 */
import { mount } from '@vue/test-utils';

import ImportExportUpdateByMappingService
    from 'src/module/sw-import-export/service/importExportUpdateByMapping.service';
// eslint-disable-next-line import/no-unresolved
import entitySchemaMock from 'src/../test/_mocks_/entity-schema.json';

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

        return mount(await wrapTestComponent('sw-import-export-edit-profile-modal-identifiers', {
            sync: true,
        }), {
            props: {
                profile,
            },
            global: {
                provide: {
                    importExportUpdateByMapping: new ImportExportUpdateByMappingService(Shopware.EntityDefinition),
                },
                stubs: {
                    'sw-data-grid': await wrapTestComponent('sw-data-grid'),
                    'sw-import-export-entity-path-select': true,
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-block-field': await wrapTestComponent('sw-block-field'),
                    'sw-select-base': await wrapTestComponent('sw-select-base'),
                    'sw-single-select': await wrapTestComponent('sw-single-select'),
                    'sw-empty-state': true,
                    'sw-icon': true,
                    'sw-field-error': true,
                    'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                    'sw-popover': await wrapTestComponent('sw-popover'),
                    'sw-popover-deprecated': await wrapTestComponent('sw-popover-deprecated', { sync: true }),
                    'sw-select-result': await wrapTestComponent('sw-select-result'),
                    'sw-highlight-text': {
                        props: ['text'],
                        template: '<div class="sw-highlight-text">{{ this.text }}</div>',
                    },
                    'sw-checkbox-field': true,
                    'sw-context-menu-item': true,
                    'sw-context-button': true,
                    'sw-data-grid-settings': true,
                    'sw-data-grid-column-boolean': true,
                    'sw-data-grid-inline-edit': true,
                    'router-link': true,
                    'sw-button': true,
                    'sw-data-grid-skeleton': true,
                    'sw-loader': true,
                    'sw-inheritance-switch': true,
                    'sw-ai-copilot-badge': true,
                    'sw-help-text': true,
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

        expect(wrapper.vm).toBeTruthy();
    });

    it('should have identifier entries for all entities in mapping', async () => {
        const profileMock = getProfileMock();

        wrapper = await createWrapper(profileMock);
        await flushPromises();

        expect(wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--identifierName').text()).toBe('product');
        expect(wrapper.find('.sw-data-grid__row--1 .sw-data-grid__cell--identifierName').text()).toBe('manufacturer');
        expect(wrapper.find('.sw-data-grid__row--2 .sw-data-grid__cell--identifierName').text()).toBe('properties');
        expect(wrapper.find('.sw-data-grid__row--3 .sw-data-grid__cell--identifierName').text()).toBe('tax');
    });


    it('should have options for entries in update by mapping', async () => {
        const profileMock = getProfileMock();

        wrapper = await createWrapper(profileMock);
        await flushPromises();

        await wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--mapped .sw-select__selection').trigger('click');
        await flushPromises();

        const productNumberOption = wrapper.find('.sw-select-option--0');
        expect(productNumberOption.exists()).toBeTruthy();

        expect(wrapper.find('.sw-data-grid__row--1 .sw-data-grid__cell--mapped .sw-single-select__selection-text').text()).toBe('translations.DEFAULT.name');

        expect(wrapper.find('.sw-data-grid__row--2 .sw-data-grid__cell--mapped sw-import-export-entity-path-select-stub').exists()).toBeTruthy();

        expect(wrapper.find('.sw-data-grid__row--3 .sw-data-grid__cell--mapped .sw-single-select__selection-text').text()).toBe('id');

        await wrapper.find('.sw-data-grid__row--3 .sw-data-grid__cell--mapped .sw-select__selection').trigger('click');
        await flushPromises();

        const taxIdOption = wrapper.find('.sw-select-result-list__item-list .sw-select-option--id');
        expect(taxIdOption.exists()).toBeTruthy();

        const taxRateOption = wrapper.find('.sw-select-result-list__item-list .sw-select-option--taxRate');
        expect(taxRateOption.exists()).toBeTruthy();
    });
});
