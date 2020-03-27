import { createLocalVue, shallowMount } from '@vue/test-utils';
import EntityDefinition from 'src/core/data-new/entity-definition.data';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-field-error';
import 'src/app/component/form/select/base/sw-select-result-list';
import 'src/app/component/utils/sw-popover';
import 'src/app/component/form/select/base/sw-select-result';
import 'src/app/component/base/sw-highlight-text';
import 'src/module/sw-settings-import-export/component/sw-import-export-entity-path-select';

describe('components/sw-import-export-entity-path-select', () => {
    let wrapper;
    let localVue;

    beforeEach(() => {
        localVue = createLocalVue();
        localVue.directive('popover', {});

        const mockSchema = {
            entity: 'product',
            properties: {
                id: {
                    type: 'uuid',
                    flags: {}
                }
            }
        };

        Shopware.EntityDefinition.get = (() => {
            return new EntityDefinition(mockSchema);
        });

        wrapper = shallowMount(Shopware.Component.build('sw-import-export-entity-path-select'), {
            localVue,
            stubs: {
                'sw-select-base': Shopware.Component.build('sw-select-base'),
                'sw-block-field': Shopware.Component.build('sw-block-field'),
                'sw-base-field': Shopware.Component.build('sw-base-field'),
                'sw-icon': '<div></div>',
                'sw-field-error': Shopware.Component.build('sw-field-error'),
                'sw-select-result-list': Shopware.Component.build('sw-select-result-list'),
                'sw-popover': Shopware.Component.build('sw-popover'),
                'sw-select-result': Shopware.Component.build('sw-select-result'),
                'sw-highlight-text': Shopware.Component.build('sw-highlight-text')
            },
            propsData: {
                value: null,
                entityType: 'product'
            }
        });
    });

    afterEach(() => {
        localVue = null;
        wrapper.destroy();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.isVueInstance()).toBeTruthy();
    });

    it('should return array when calling `actualPathParts` computed property', () => {
        wrapper.setProps({
            value: 'media.id.'
        });

        expect(wrapper.vm.actualPathParts).toEqual(['media', 'id']);
    });

    it('should return valid price properties on `getPriceProperties` with given currencies', () => {
        wrapper.setProps({
            currencies: [
                { isoCode: 'EUR' },
                { isoCode: 'USD' }
            ]
        });

        const actual = wrapper.vm.getPriceProperties('price.');
        const expected = [
            { label: 'price.EUR.net', value: 'price.EUR.net' },
            { label: 'price.EUR.gross', value: 'price.EUR.gross' },
            { label: 'price.EUR.currencyId', value: 'price.EUR.currencyId' },
            { label: 'price.EUR.linked', value: 'price.EUR.linked' },
            { label: 'price.EUR.listPrice', value: 'price.EUR.listPrice' },
            { label: 'price.USD.net', value: 'price.USD.net' },
            { label: 'price.USD.gross', value: 'price.USD.gross' },
            { label: 'price.USD.currencyId', value: 'price.USD.currencyId' },
            { label: 'price.USD.linked', value: 'price.USD.linked' },
            { label: 'price.USD.listPrice', value: 'price.USD.listPrice' }
        ];

        expect(actual).toEqual(expected);
    });

    it('should return valid price properties when getting price properties without given currencies', () => {
        const actual = wrapper.vm.getPriceProperties('price.');
        const expected = [
            { label: 'price.DEFAULT.net', value: 'price.DEFAULT.net' },
            { label: 'price.DEFAULT.gross', value: 'price.DEFAULT.gross' },
            { label: 'price.DEFAULT.currencyId', value: 'price.DEFAULT.currencyId' },
            { label: 'price.DEFAULT.linked', value: 'price.DEFAULT.linked' },
            { label: 'price.DEFAULT.listPrice', value: 'price.DEFAULT.listPrice' }
        ];

        expect(actual).toEqual(expected);
    });

    it('should return valid visibility properties on `getVisibilityProperties` with given visibilities', () => {
        wrapper.setData({
            visibilityProperties: ['all', 'link', 'search']
        });

        const actual = wrapper.vm.getVisibilityProperties('visibilities.');
        const expected = [
            { label: 'visibilities.all', value: 'visibilities.all' },
            { label: 'visibilities.link', value: 'visibilities.link' },
            { label: 'visibilities.search', value: 'visibilities.search' }
        ];

        expect(actual).toEqual(expected);
    });

    it('should return valid translation properties on `getTranslationProperties', () => {
        const mockProperties = [
            'metaDescription',
            'keywords',
            'description'
        ];

        wrapper.setProps({
            languages: [
                { locale: { code: 'en-GB' } },
                { locale: { code: 'de-DE' } }
            ]
        });

        const actual = wrapper.vm.getTranslationProperties('product_translation', 'translations.', mockProperties);
        const expected = [
            { label: 'translations.en-GB.metaDescription', value: 'translations.en-GB.metaDescription' },
            { label: 'translations.en-GB.keywords', value: 'translations.en-GB.keywords' },
            { label: 'translations.en-GB.description', value: 'translations.en-GB.description' },
            { label: 'translations.de-DE.metaDescription', value: 'translations.de-DE.metaDescription' },
            { label: 'translations.de-DE.keywords', value: 'translations.de-DE.keywords' },
            { label: 'translations.de-DE.description', value: 'translations.de-DE.description' }
        ];

        expect(actual).toEqual(expected);
    });
});
