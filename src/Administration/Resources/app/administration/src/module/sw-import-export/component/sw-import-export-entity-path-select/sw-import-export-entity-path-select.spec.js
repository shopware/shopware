/**
 * @package services-settings
 * @group disabledCompat
 */
import { mount } from '@vue/test-utils';

async function createWrapper(entityType = 'product') {
    return mount(await wrapTestComponent('sw-import-export-entity-path-select', { sync: true }), {
        global: {
            stubs: {
                'sw-select-base': await wrapTestComponent('sw-select-base'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-icon': {
                    template: '<div></div>',
                },
                'sw-field-error': await wrapTestComponent('sw-field-error'),
                'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                'sw-popover': await wrapTestComponent('sw-popover'),
                'sw-popover-deprecated': await wrapTestComponent('sw-popover-deprecated', { sync: true }),
                'sw-select-result': await wrapTestComponent('sw-select-result'),
                'sw-highlight-text': await wrapTestComponent('sw-highlight-text'),
                transition: false,
                'sw-loader': true,
                'sw-inheritance-switch': true,
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
            },
        },
        props: {
            value: null,
            entityType: entityType,
            customFieldSets: [
                {
                    relations: [{ entityName: 'product' }],
                    customFields: [{ name: 'custom_field_product_1' }, { name: 'custom_field_product_2' }],
                },
                {
                    relations: [{ entityName: 'product_manufacturer' }],
                    customFields: [{ name: 'custom_field_manufacturer_1' }, { name: 'custom_field_manufacturer_2' }],
                },
            ],
        },
    });
}

describe('module/sw-import-export/components/sw-import-export-entity-path-select', () => {
    afterEach(async () => {
        jest.clearAllTimers();
    });

    it('should return array when calling `actualPathParts` computed property', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setProps({
            value: 'media.id.',
        });

        expect(wrapper.vm.actualPathParts).toEqual(['media', 'id']);
    });

    it('should return valid price properties on `getPriceProperties` with given currencies', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setProps({
            currencies: [
                { isoCode: 'EUR' },
                { isoCode: 'USD' },
            ],
        });

        const actual = wrapper.vm.getPriceProperties('');
        const expected = [
            { label: 'price.EUR.net', value: 'price.EUR.net' },
            { label: 'price.EUR.gross', value: 'price.EUR.gross' },
            { label: 'price.EUR.currencyId', value: 'price.EUR.currencyId' },
            { label: 'price.EUR.linked', value: 'price.EUR.linked' },
            { label: 'price.EUR.listPrice.net', value: 'price.EUR.listPrice.net' },
            { label: 'price.EUR.listPrice.gross', value: 'price.EUR.listPrice.gross' },
            { label: 'price.EUR.listPrice.linked', value: 'price.EUR.listPrice.linked' },
            { label: 'price.USD.net', value: 'price.USD.net' },
            { label: 'price.USD.gross', value: 'price.USD.gross' },
            { label: 'price.USD.currencyId', value: 'price.USD.currencyId' },
            { label: 'price.USD.linked', value: 'price.USD.linked' },
            { label: 'price.USD.listPrice.net', value: 'price.USD.listPrice.net' },
            { label: 'price.USD.listPrice.gross', value: 'price.USD.listPrice.gross' },
            { label: 'price.USD.listPrice.linked', value: 'price.USD.listPrice.linked' },
            { label: 'purchasePrices.EUR.net', value: 'purchasePrices.EUR.net' },
            { label: 'purchasePrices.EUR.gross', value: 'purchasePrices.EUR.gross' },
            { label: 'purchasePrices.EUR.currencyId', value: 'purchasePrices.EUR.currencyId' },
            { label: 'purchasePrices.EUR.linked', value: 'purchasePrices.EUR.linked' },
            { label: 'purchasePrices.EUR.listPrice.net', value: 'purchasePrices.EUR.listPrice.net' },
            { label: 'purchasePrices.EUR.listPrice.gross', value: 'purchasePrices.EUR.listPrice.gross' },
            { label: 'purchasePrices.EUR.listPrice.linked', value: 'purchasePrices.EUR.listPrice.linked' },
            { label: 'purchasePrices.USD.net', value: 'purchasePrices.USD.net' },
            { label: 'purchasePrices.USD.gross', value: 'purchasePrices.USD.gross' },
            { label: 'purchasePrices.USD.currencyId', value: 'purchasePrices.USD.currencyId' },
            { label: 'purchasePrices.USD.linked', value: 'purchasePrices.USD.linked' },
            { label: 'purchasePrices.USD.listPrice.net', value: 'purchasePrices.USD.listPrice.net' },
            { label: 'purchasePrices.USD.listPrice.gross', value: 'purchasePrices.USD.listPrice.gross' },
            { label: 'purchasePrices.USD.listPrice.linked', value: 'purchasePrices.USD.listPrice.linked' },
        ];

        expect(actual).toEqual(expected);
    });

    it('should return valid price properties on `getPriceProperties` with given currencies and path set', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setProps({
            currencies: [
                { isoCode: 'EUR' },
                { isoCode: 'USD' },
            ],
        });

        const actual = wrapper.vm.getPriceProperties('parent.');
        const expected = [
            { label: 'parent.price.EUR.net', value: 'parent.price.EUR.net' },
            { label: 'parent.price.EUR.gross', value: 'parent.price.EUR.gross' },
            { label: 'parent.price.EUR.currencyId', value: 'parent.price.EUR.currencyId' },
            { label: 'parent.price.EUR.linked', value: 'parent.price.EUR.linked' },
            { label: 'parent.price.EUR.listPrice.net', value: 'parent.price.EUR.listPrice.net' },
            { label: 'parent.price.EUR.listPrice.gross', value: 'parent.price.EUR.listPrice.gross' },
            { label: 'parent.price.EUR.listPrice.linked', value: 'parent.price.EUR.listPrice.linked' },
            { label: 'parent.price.USD.net', value: 'parent.price.USD.net' },
            { label: 'parent.price.USD.gross', value: 'parent.price.USD.gross' },
            { label: 'parent.price.USD.currencyId', value: 'parent.price.USD.currencyId' },
            { label: 'parent.price.USD.linked', value: 'parent.price.USD.linked' },
            { label: 'parent.price.USD.listPrice.net', value: 'parent.price.USD.listPrice.net' },
            { label: 'parent.price.USD.listPrice.gross', value: 'parent.price.USD.listPrice.gross' },
            { label: 'parent.price.USD.listPrice.linked', value: 'parent.price.USD.listPrice.linked' },
            { label: 'parent.purchasePrices.EUR.net', value: 'parent.purchasePrices.EUR.net' },
            { label: 'parent.purchasePrices.EUR.gross', value: 'parent.purchasePrices.EUR.gross' },
            { label: 'parent.purchasePrices.EUR.currencyId', value: 'parent.purchasePrices.EUR.currencyId' },
            { label: 'parent.purchasePrices.EUR.linked', value: 'parent.purchasePrices.EUR.linked' },
            { label: 'parent.purchasePrices.EUR.listPrice.net', value: 'parent.purchasePrices.EUR.listPrice.net' },
            { label: 'parent.purchasePrices.EUR.listPrice.gross', value: 'parent.purchasePrices.EUR.listPrice.gross' },
            { label: 'parent.purchasePrices.EUR.listPrice.linked', value: 'parent.purchasePrices.EUR.listPrice.linked' },
            { label: 'parent.purchasePrices.USD.net', value: 'parent.purchasePrices.USD.net' },
            { label: 'parent.purchasePrices.USD.gross', value: 'parent.purchasePrices.USD.gross' },
            { label: 'parent.purchasePrices.USD.currencyId', value: 'parent.purchasePrices.USD.currencyId' },
            { label: 'parent.purchasePrices.USD.linked', value: 'parent.purchasePrices.USD.linked' },
            { label: 'parent.purchasePrices.USD.listPrice.net', value: 'parent.purchasePrices.USD.listPrice.net' },
            { label: 'parent.purchasePrices.USD.listPrice.gross', value: 'parent.purchasePrices.USD.listPrice.gross' },
            { label: 'parent.purchasePrices.USD.listPrice.linked', value: 'parent.purchasePrices.USD.listPrice.linked' },
        ];

        expect(actual).toEqual(expected);
    });

    it('should return valid price properties when getting price properties without given currencies', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const actual = wrapper.vm.getPriceProperties('');
        const expected = [
            { label: 'price.DEFAULT.net', value: 'price.DEFAULT.net' },
            { label: 'price.DEFAULT.gross', value: 'price.DEFAULT.gross' },
            { label: 'price.DEFAULT.currencyId', value: 'price.DEFAULT.currencyId' },
            { label: 'price.DEFAULT.linked', value: 'price.DEFAULT.linked' },
            { label: 'price.DEFAULT.listPrice.net', value: 'price.DEFAULT.listPrice.net' },
            { label: 'price.DEFAULT.listPrice.gross', value: 'price.DEFAULT.listPrice.gross' },
            { label: 'price.DEFAULT.listPrice.linked', value: 'price.DEFAULT.listPrice.linked' },
            { label: 'purchasePrices.DEFAULT.net', value: 'purchasePrices.DEFAULT.net' },
            { label: 'purchasePrices.DEFAULT.gross', value: 'purchasePrices.DEFAULT.gross' },
            { label: 'purchasePrices.DEFAULT.currencyId', value: 'purchasePrices.DEFAULT.currencyId' },
            { label: 'purchasePrices.DEFAULT.linked', value: 'purchasePrices.DEFAULT.linked' },
            { label: 'purchasePrices.DEFAULT.listPrice.net', value: 'purchasePrices.DEFAULT.listPrice.net' },
            { label: 'purchasePrices.DEFAULT.listPrice.gross', value: 'purchasePrices.DEFAULT.listPrice.gross' },
            { label: 'purchasePrices.DEFAULT.listPrice.linked', value: 'purchasePrices.DEFAULT.listPrice.linked' },
        ];

        expect(actual).toEqual(expected);
    });

    it('should return valid visibility properties on `getVisibilityProperties` with given visibilities', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const actual = wrapper.vm.getVisibilityProperties('');
        const expected = [
            { label: 'visibilities.all', value: 'visibilities.all' },
            { label: 'visibilities.link', value: 'visibilities.link' },
            { label: 'visibilities.search', value: 'visibilities.search' },
        ];

        expect(actual).toEqual(expected);
    });

    it('should return valid translation properties on `getTranslationProperties', async () => {
        const mockProperties = [
            'metaDescription',
            'keywords',
            'description',
        ];

        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setProps({
            languages: [
                { locale: { code: 'en-GB' } },
                { locale: { code: 'de-DE' } },
                { locale: { code: 'DEFAULT' } },
            ],
        });

        const actual = wrapper.vm.getTranslationProperties('', mockProperties);

        const expected = [
            { label: 'translations.en-GB.metaDescription', value: 'translations.en-GB.metaDescription' },
            { label: 'translations.en-GB.keywords', value: 'translations.en-GB.keywords' },
            { label: 'translations.en-GB.description', value: 'translations.en-GB.description' },
            { label: 'translations.de-DE.metaDescription', value: 'translations.de-DE.metaDescription' },
            { label: 'translations.de-DE.keywords', value: 'translations.de-DE.keywords' },
            { label: 'translations.de-DE.description', value: 'translations.de-DE.description' },
            { label: 'translations.DEFAULT.metaDescription', value: 'translations.DEFAULT.metaDescription' },
            { label: 'translations.DEFAULT.keywords', value: 'translations.DEFAULT.keywords' },
            { label: 'translations.DEFAULT.description', value: 'translations.DEFAULT.description' },
        ];

        expect(actual).toEqual(expected);
    });

    it('should return media properties for product cover media value', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setProps({
            value: 'cover.media.',
            languages: [
                { locale: { code: 'en-GB' } },
                { locale: { code: 'de-DE' } },
                { locale: { code: 'DEFAULT' } },
            ],
        });

        const actual = wrapper.vm.visibleResults;

        const expected = [
            {
                label: 'cover.media.id',
                value: 'cover.media.id',
                relation: undefined,
            },
            {
                label: 'cover.media.translations.DEFAULT.title',
                value: 'cover.media.translations.DEFAULT.title',
            },
            {
                label: 'cover.media.translations.de-DE.title',
                value: 'cover.media.translations.de-DE.title',
            },
            {
                label: 'cover.media.translations.en-GB.title',
                value: 'cover.media.translations.en-GB.title',
            },
        ];
        expected.forEach(value => expect(actual).toContainEqual(value));
    });

    it('should return product translation properties for product parent parent translation value', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setProps({
            value: 'parent.parent.translations.name',
            languages: [
                { locale: { code: 'en-GB' } },
                { locale: { code: 'de-DE' } },
                { locale: { code: 'DEFAULT' } },
            ],
        });

        const actual = wrapper.vm.visibleResults;

        const expected = [
            {
                label: 'parent.parent.translations.en-GB.name',
                value: 'parent.parent.translations.en-GB.name',
            },
            {
                label: 'parent.parent.translations.de-DE.name',
                value: 'parent.parent.translations.de-DE.name',
            },
            {
                label: 'parent.parent.translations.DEFAULT.name',
                value: 'parent.parent.translations.DEFAULT.name',
            },
        ];

        expect(actual).toEqual(expect.arrayContaining(expected));
    });


    it('should return nothing for searching a invalid path', async () => {
        jest.useFakeTimers();

        const wrapper = await createWrapper();
        await flushPromises();

        const input = wrapper.find('.sw-import-export-entity-path-select__selection-input');

        await input.trigger('click');
        await input.setValue('foo');
        jest.advanceTimersByTime(300);
        await flushPromises();

        expect(wrapper.find('.sw-select-result-list__empty').text()).toBeTruthy();

        await input.setValue('foo.');
        jest.advanceTimersByTime(300);
        await flushPromises();

        expect(wrapper.find('.sw-select-result-list__empty').text()).toBeTruthy();


        await input.setValue('parent.foo.');
        jest.advanceTimersByTime(300);
        await flushPromises();

        expect(wrapper.find('.sw-select-result-list__empty').text()).toBeTruthy();
    });

    it('should return filtered product properties when searching', async () => {
        jest.useFakeTimers();
        const wrapper = await createWrapper();
        await flushPromises();

        const input = wrapper.find('.sw-import-export-entity-path-select__selection-input');

        await input.trigger('click');
        await input.setValue('parent.parent.price.');
        jest.advanceTimersByTime(300);
        await flushPromises();

        const actual = wrapper.vm.visibleResults;

        const expected = [
            {
                label: 'parent.parent.price.DEFAULT.currencyId',
                value: 'parent.parent.price.DEFAULT.currencyId',
            },
            {
                label: 'parent.parent.price.DEFAULT.gross',
                value: 'parent.parent.price.DEFAULT.gross',
            },
            {
                label: 'parent.parent.price.DEFAULT.linked',
                value: 'parent.parent.price.DEFAULT.linked',
            },
            {
                label: 'parent.parent.price.DEFAULT.listPrice.gross',
                value: 'parent.parent.price.DEFAULT.listPrice.gross',
            },
            {
                label: 'parent.parent.price.DEFAULT.listPrice.linked',
                value: 'parent.parent.price.DEFAULT.listPrice.linked',
            },
            {
                label: 'parent.parent.price.DEFAULT.listPrice.net',
                value: 'parent.parent.price.DEFAULT.listPrice.net',
            },
            {
                label: 'parent.parent.price.DEFAULT.net',
                value: 'parent.parent.price.DEFAULT.net',
            },
            {
                label: 'parent.parent.purchasePrices.DEFAULT.currencyId',
                value: 'parent.parent.purchasePrices.DEFAULT.currencyId',
            },
            {
                label: 'parent.parent.purchasePrices.DEFAULT.gross',
                value: 'parent.parent.purchasePrices.DEFAULT.gross',
            },
            {
                label: 'parent.parent.purchasePrices.DEFAULT.linked',
                value: 'parent.parent.purchasePrices.DEFAULT.linked',
            },
            {
                label: 'parent.parent.purchasePrices.DEFAULT.listPrice.gross',
                value: 'parent.parent.purchasePrices.DEFAULT.listPrice.gross',
            },
            {
                label: 'parent.parent.purchasePrices.DEFAULT.listPrice.linked',
                value: 'parent.parent.purchasePrices.DEFAULT.listPrice.linked',
            },
            {
                label: 'parent.parent.purchasePrices.DEFAULT.listPrice.net',
                value: 'parent.parent.purchasePrices.DEFAULT.listPrice.net',
            },
            {
                label: 'parent.parent.purchasePrices.DEFAULT.net',
                value: 'parent.parent.purchasePrices.DEFAULT.net',
            },
        ];

        expect(actual).toEqual(expected);
    });

    it('should process translations, prices visibilities and remove property from properties array', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setProps({
            value: '',
            languages: [
                { locale: { code: 'DEFAULT' } },
            ],
        });

        const definition = Shopware.EntityDefinition.get('product');

        let data = {
            definition: definition,
            options: [],
            properties: Object.keys(definition.properties),
            path: '',
        };

        [
            'id',
            'price',
            'parent',
            'cover',
            'name',
            'manufacturer',
            'translations',
            'visibilities',
        ].forEach(property => expect(data.properties).toContain(property));

        data = wrapper.vm.processTranslations(data);

        [
            'id',
            'price',
            'parent',
            'cover',
            'manufacturer',
            'visibilities',
        ].forEach(property => expect(data.properties).toContain(property));
        expect(data.options).toEqual([
            { label: 'translations.DEFAULT.metaDescription', value: 'translations.DEFAULT.metaDescription' },
            { label: 'translations.DEFAULT.name', value: 'translations.DEFAULT.name' },
            { label: 'translations.DEFAULT.keywords', value: 'translations.DEFAULT.keywords' },
            { label: 'translations.DEFAULT.description', value: 'translations.DEFAULT.description' },
            { label: 'translations.DEFAULT.metaTitle', value: 'translations.DEFAULT.metaTitle' },
            { label: 'translations.DEFAULT.packUnit', value: 'translations.DEFAULT.packUnit' },
            { label: 'translations.DEFAULT.packUnitPlural', value: 'translations.DEFAULT.packUnitPlural' },
            { label: 'translations.DEFAULT.customSearchKeywords', value: 'translations.DEFAULT.customSearchKeywords' },
            { label: 'translations.DEFAULT.slotConfig', value: 'translations.DEFAULT.slotConfig' },
            { label: 'translations.DEFAULT.customFields', value: 'translations.DEFAULT.customFields', relation: true },
            { label: 'translations.DEFAULT.createdAt', value: 'translations.DEFAULT.createdAt' },
            { label: 'translations.DEFAULT.updatedAt', value: 'translations.DEFAULT.updatedAt' },
            { label: 'translations.DEFAULT.productId', value: 'translations.DEFAULT.productId' },
            { label: 'translations.DEFAULT.languageId', value: 'translations.DEFAULT.languageId' },
            { label: 'translations.DEFAULT.product', value: 'translations.DEFAULT.product' },
            { label: 'translations.DEFAULT.language', value: 'translations.DEFAULT.language' },
            { label: 'translations.DEFAULT.productVersionId', value: 'translations.DEFAULT.productVersionId' },
        ]);

        data = wrapper.vm.processVisibilities(data);

        [
            'id',
            'price',
            'parent',
            'cover',
            'manufacturer',
        ].forEach(property => expect(data.properties).toContain(property));
        expect(data.options).toEqual([
            { label: 'translations.DEFAULT.metaDescription', value: 'translations.DEFAULT.metaDescription' },
            { label: 'translations.DEFAULT.name', value: 'translations.DEFAULT.name' },
            { label: 'translations.DEFAULT.keywords', value: 'translations.DEFAULT.keywords' },
            { label: 'translations.DEFAULT.description', value: 'translations.DEFAULT.description' },
            { label: 'translations.DEFAULT.metaTitle', value: 'translations.DEFAULT.metaTitle' },
            { label: 'translations.DEFAULT.packUnit', value: 'translations.DEFAULT.packUnit' },
            { label: 'translations.DEFAULT.packUnitPlural', value: 'translations.DEFAULT.packUnitPlural' },
            { label: 'translations.DEFAULT.customSearchKeywords', value: 'translations.DEFAULT.customSearchKeywords' },
            { label: 'translations.DEFAULT.slotConfig', value: 'translations.DEFAULT.slotConfig' },
            { label: 'translations.DEFAULT.customFields', value: 'translations.DEFAULT.customFields', relation: true },
            { label: 'translations.DEFAULT.createdAt', value: 'translations.DEFAULT.createdAt' },
            { label: 'translations.DEFAULT.updatedAt', value: 'translations.DEFAULT.updatedAt' },
            { label: 'translations.DEFAULT.productId', value: 'translations.DEFAULT.productId' },
            { label: 'translations.DEFAULT.languageId', value: 'translations.DEFAULT.languageId' },
            { label: 'translations.DEFAULT.product', value: 'translations.DEFAULT.product' },
            { label: 'translations.DEFAULT.language', value: 'translations.DEFAULT.language' },
            { label: 'translations.DEFAULT.productVersionId', value: 'translations.DEFAULT.productVersionId' },
            { label: 'visibilities.all', value: 'visibilities.all' },
            { label: 'visibilities.link', value: 'visibilities.link' },
            { label: 'visibilities.search', value: 'visibilities.search' },
        ]);

        data = wrapper.vm.processPrice(data);

        [
            'id',
            'parent',
            'cover',
            'manufacturer',
        ].forEach(property => expect(data.properties).toContain(property));
        expect(data.options).toEqual([
            { label: 'translations.DEFAULT.metaDescription', value: 'translations.DEFAULT.metaDescription' },
            { label: 'translations.DEFAULT.name', value: 'translations.DEFAULT.name' },
            { label: 'translations.DEFAULT.keywords', value: 'translations.DEFAULT.keywords' },
            { label: 'translations.DEFAULT.description', value: 'translations.DEFAULT.description' },
            { label: 'translations.DEFAULT.metaTitle', value: 'translations.DEFAULT.metaTitle' },
            { label: 'translations.DEFAULT.packUnit', value: 'translations.DEFAULT.packUnit' },
            { label: 'translations.DEFAULT.packUnitPlural', value: 'translations.DEFAULT.packUnitPlural' },
            { label: 'translations.DEFAULT.customSearchKeywords', value: 'translations.DEFAULT.customSearchKeywords' },
            { label: 'translations.DEFAULT.slotConfig', value: 'translations.DEFAULT.slotConfig' },
            { label: 'translations.DEFAULT.customFields', value: 'translations.DEFAULT.customFields', relation: true },
            { label: 'translations.DEFAULT.createdAt', value: 'translations.DEFAULT.createdAt' },
            { label: 'translations.DEFAULT.updatedAt', value: 'translations.DEFAULT.updatedAt' },
            { label: 'translations.DEFAULT.productId', value: 'translations.DEFAULT.productId' },
            { label: 'translations.DEFAULT.languageId', value: 'translations.DEFAULT.languageId' },
            { label: 'translations.DEFAULT.product', value: 'translations.DEFAULT.product' },
            { label: 'translations.DEFAULT.language', value: 'translations.DEFAULT.language' },
            { label: 'translations.DEFAULT.productVersionId', value: 'translations.DEFAULT.productVersionId' },
            { label: 'visibilities.all', value: 'visibilities.all' },
            { label: 'visibilities.link', value: 'visibilities.link' },
            { label: 'visibilities.search', value: 'visibilities.search' },
            { label: 'price.DEFAULT.net', value: 'price.DEFAULT.net' },
            { label: 'price.DEFAULT.gross', value: 'price.DEFAULT.gross' },
            { label: 'price.DEFAULT.currencyId', value: 'price.DEFAULT.currencyId' },
            { label: 'price.DEFAULT.linked', value: 'price.DEFAULT.linked' },
            { label: 'price.DEFAULT.listPrice.net', value: 'price.DEFAULT.listPrice.net' },
            { label: 'price.DEFAULT.listPrice.gross', value: 'price.DEFAULT.listPrice.gross' },
            { label: 'price.DEFAULT.listPrice.linked', value: 'price.DEFAULT.listPrice.linked' },
            { label: 'purchasePrices.DEFAULT.net', value: 'purchasePrices.DEFAULT.net' },
            { label: 'purchasePrices.DEFAULT.gross', value: 'purchasePrices.DEFAULT.gross' },
            { label: 'purchasePrices.DEFAULT.currencyId', value: 'purchasePrices.DEFAULT.currencyId' },
            { label: 'purchasePrices.DEFAULT.linked', value: 'purchasePrices.DEFAULT.linked' },
            { label: 'purchasePrices.DEFAULT.listPrice.net', value: 'purchasePrices.DEFAULT.listPrice.net' },
            { label: 'purchasePrices.DEFAULT.listPrice.gross', value: 'purchasePrices.DEFAULT.listPrice.gross' },
            { label: 'purchasePrices.DEFAULT.listPrice.linked', value: 'purchasePrices.DEFAULT.listPrice.linked' },
        ]);
    });

    it('should process assignedProducts and remove property from properties array', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setProps({
            value: '',
        });

        const definition = Shopware.EntityDefinition.get('product_cross_selling');

        let data = {
            definition: definition,
            options: [],
            properties: Object.keys(definition.properties),
            path: '',
        };
        data = wrapper.vm.processTranslations(data);

        expect(data.properties).toContain('assignedProducts');

        data = wrapper.vm.processAssignedProducts(data);

        expect(data.properties).not.toContain('assignedProducts');

        expect(data.options).toContainEqual({ label: 'assignedProducts', value: 'assignedProducts' });
        expect(data.options).toContainEqual({ label: 'translations.DEFAULT.name', value: 'translations.DEFAULT.name' });
    });

    it('should sort options', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const options = [
            { label: 'name', value: 'name' },
            { label: 'media', value: 'media' },
            { label: 'media', value: 'media' },
            { label: 'id', value: 'id' },
            { label: 'cover', value: 'cover' },
        ];

        const actual = options.sort(wrapper.vm.sortOptions);

        expect(actual).toEqual([
            { label: 'cover', value: 'cover' },
            { label: 'id', value: 'id' },
            { label: 'media', value: 'media' },
            { label: 'media', value: 'media' },
            { label: 'name', value: 'name' },
        ]);
    });

    it('should return custom field options by entity name', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        let actual = wrapper.vm.getCustomFields('product');
        let expected = {
            custom_field_product_1: { label: 'custom_field_product_1', value: 'custom_field_product_1' },
            custom_field_product_2: { label: 'custom_field_product_2', value: 'custom_field_product_2' },
        };

        expect(actual).toEqual(expected);

        actual = wrapper.vm.getCustomFields('product_manufacturer');
        expected = {
            custom_field_manufacturer_1: { label: 'custom_field_manufacturer_1', value: 'custom_field_manufacturer_1' },
            custom_field_manufacturer_2: { label: 'custom_field_manufacturer_2', value: 'custom_field_manufacturer_2' },
        };

        expect(actual).toEqual(expected);
    });

    it('should show custom field options if selected value is custom field', async () => {
        jest.useFakeTimers();

        const wrapper = await createWrapper();
        await flushPromises();

        const inputField = wrapper.find('.sw-import-export-entity-path-select__selection-input');

        await inputField.trigger('click');
        await inputField.setValue('manufacturer.translations.DEFAULT.customFields.');
        jest.advanceTimersByTime(300);
        await flushPromises();

        const actual = wrapper.vm.visibleResults;
        const expected = [
            {
                label: 'manufacturer.translations.DEFAULT.customFields.custom_field_manufacturer_1',
                value: 'manufacturer.translations.DEFAULT.customFields.custom_field_manufacturer_1',
                relation: undefined,
            },
            {
                label: 'manufacturer.translations.DEFAULT.customFields.custom_field_manufacturer_2',
                value: 'manufacturer.translations.DEFAULT.customFields.custom_field_manufacturer_2',
                relation: undefined,
            },
        ];

        expect(actual).toEqual(expected);
    });

    it('should show transactions of an order on search', async () => {
        jest.useFakeTimers();

        const wrapper = await createWrapper('order');
        await flushPromises();

        const input = wrapper.find('.sw-import-export-entity-path-select__selection-input');
        await input.trigger('click');
        await input.setValue('transactions');
        jest.advanceTimersByTime(300);
        await flushPromises();

        const selectResults = wrapper.findAll('.sw-select-result')
            .map(element => element.text());
        expect(selectResults).toStrictEqual([
            'sw-import-export.profile.mapping.notMapped',
            'transactions.amount',
            'transactions.captures',
            'transactions.createdAt',
            'transactions.customFields',
            'transactions.id',
            'transactions.order',
            'transactions.orderId',
            'transactions.orderVersionId',
            'transactions.paymentMethod',
            'transactions.paymentMethodId',
            'transactions.stateId',
            'transactions.stateMachineState',
            'transactions.updatedAt',
            'transactions.validationData',
            'transactions.versionId',
        ]);
    });

    it('should show deliveries of an order on search', async () => {
        jest.useFakeTimers();

        const wrapper = await createWrapper('order');
        await flushPromises();

        const input = wrapper.find('.sw-import-export-entity-path-select__selection-input');

        await input.trigger('click');
        await input.setValue('deliveries');
        jest.advanceTimersByTime(300);
        await flushPromises();

        const selectResults = wrapper.findAll('.sw-select-result')
            .map(element => element.text());
        expect(selectResults).toStrictEqual([
            'sw-import-export.profile.mapping.notMapped',
            'deliveries.createdAt',
            'deliveries.customFields',
            'deliveries.id',
            'deliveries.order',
            'deliveries.orderId',
            'deliveries.orderVersionId',
            'deliveries.positions',
            'deliveries.shippingCosts',
            'deliveries.shippingDateEarliest',
            'deliveries.shippingDateLatest',
            'deliveries.shippingMethod',
            'deliveries.shippingMethodId',
            'deliveries.shippingOrderAddress',
            'deliveries.shippingOrderAddressId',
            'deliveries.shippingOrderAddressVersionId',
            'deliveries.stateId',
            'deliveries.stateMachineState',
            'deliveries.trackingCodes',
            'deliveries.updatedAt',
            'deliveries.versionId',
        ]);
    });

    it('should add popover classes to the result list', async () => {
        const wrapper = await createWrapper('order');
        await flushPromises();

        await wrapper.find('.sw-import-export-entity-path-select__selection-input').trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-select-result-list .sw-popover__wrapper').classes()).toContain(
            'sw-import-export-entity-path-select__result-list',
        );
    });
});
