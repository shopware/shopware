/* eslint-disable sw-test-rules/test-file-max-lines-warning */

/**
 * @sw-package discovery
 */

import { mount } from '@vue/test-utils';
import { MtUrlField } from '@shopware-ag/meteor-component-library';

const { Context } = Shopware;
const { EntityCollection } = Shopware.Data;

async function createWrapper(customProps = {}, domains = []) {
    return mount(
        await wrapTestComponent('sw-sales-channel-detail-domains', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'mt-card': {
                        template: '<div><slot></slot><slot name="grid"></slot></div>',
                    },
                    'sw-data-grid': await wrapTestComponent('sw-data-grid', {
                        sync: true,
                    }),
                    'sw-context-menu-item': true,
                    'sw-context-button': true,
                    'sw-modal': await wrapTestComponent('sw-modal', {
                        sync: true,
                    }),
                    'sw-entity-single-select': {
                        template: '<div><slot></slot></div>',
                        props: [
                            'value',
                            'entity',
                            'criteria',
                            'labelProperty',
                        ],
                    },
                    'sw-grouped-single-select': {
                        template: '<div><slot></slot></div>',
                        props: [
                            'value',
                            'options',
                            'groups',
                            'searchFunction',
                        ],
                    },
                    'sw-radio-field': true,
                    'sw-single-select': await wrapTestComponent('sw-single-select', { sync: true }),
                    'sw-container': {
                        template: '<div class="sw-container"><slot></slot></div>',
                    },
                    'sw-select-base': true,
                    'sw-select-result-list': true,
                    'sw-checkbox-field': true,
                    'sw-data-grid-settings': true,
                    'sw-data-grid-column-boolean': true,
                    'sw-data-grid-inline-edit': true,
                    'router-link': true,
                    'sw-data-grid-skeleton': true,
                    'sw-loader': true,
                    'sw-highlight-text': true,
                    'sw-select-result': true,
                    'sw-provide': { template: `<slot/>`, inheritAttrs: false },
                    'mt-url-field': MtUrlField,
                    'sw-sales-channel-measurement': true,
                    'mt-checkbox': true,
                },
                provide: {
                    repositoryFactory: {
                        create: (entity) => ({
                            create: () => {
                                return {
                                    id: '44de136acf314e7184401d36406c1e90',
                                    isNew: () => true,
                                };
                            },

                            search: () =>
                                Promise.resolve(
                                    new EntityCollection(null, entity, Context.api, null, getSearchResults(entity)),
                                ),
                        }),
                    },
                    shortcutService: {
                        stopEventListener: () => {},
                        startEventListener: () => {},
                    },
                },
            },
            props: {
                salesChannel: {
                    domains: domains,
                },
                ...customProps,
            },
        },
    );
}

function getSearchResults(entity) {
    if (entity === 'snippet_set') {
        return getExampleSnippetSets();
    }

    if (entity === 'locale') {
        return getExampleLocales();
    }

    return [
        {
            id: 'metric',
            name: 'Metric system',
            translated: { name: 'Metric system' },
            technicalName: 'metric',
        },
        {
            id: 'imperial',
            name: 'Imperial system',
            translated: { name: 'Imperial system' },
            technicalName: 'imperial',
        },
    ];
}

function getExampleLocales() {
    return [
        { id: 'locale-de-de', code: 'de-DE', name: 'German', translated: { name: 'Deutsch' } },
        { id: 'locale-de-ch', code: 'de-CH', name: 'German', translated: { name: 'Deutsch' } },
        { id: 'locale-en-gb', code: 'en-GB', name: 'English', translated: { name: 'English' } },
        { id: 'locale-en-au', code: 'en-AU', name: 'English', translated: { name: 'English' } },
        { id: 'locale-fr-fr', code: 'fr-FR', name: 'French', translated: { name: 'Français' } },
    ];
}

function getExampleSnippetSets() {
    return [
        { id: 'set-fr-fr', name: 'BASE fr-FR', iso: 'fr-FR' },
        { id: 'set-es-es', name: 'BASE es-ES', iso: 'es-ES' },
        { id: 'set-en-us', name: 'BASE en-US', iso: 'en-US' },
        { id: 'set-de-ch', name: 'BASE de-CH', iso: 'de-CH' },
        { id: 'set-en-gb', name: 'BASE en-GB', iso: 'en-GB' },
        { id: 'set-de-de', name: 'BASE de-DE', iso: 'de-DE' },
        { id: 'set-de-du', name: 'Deutsch (Du)', iso: 'de-DE' },
    ];
}

function getExampleLanguages() {
    return new EntityCollection('/languages', 'languages', Context.api, null, [
        { id: 'language-de', name: 'Deutsch', localeId: 'locale-de-de' },
        { id: 'language-en', name: 'English', localeId: 'locale-en-gb' },
        { id: 'language-en-au', name: 'English (Australia)', localeId: 'locale-en-au' },
    ]);
}

function getExampleDomains() {
    return [
        {
            id: '98432def39fc4624b33213a56b8c944f',
            url: 'http://secondExample.com',
            language: {
                name: 'Deutsch',
            },
            currency: {
                name: 'Danish krone',
                translated: {
                    name: 'Danish krone',
                },
            },
            snippetSet: {
                name: 'BASE de-DE',
            },
            measurementUnits: {
                system: 'metric',
            },
            isNew: () => false,
        },
        {
            id: '66804d24057f4d4fb683a7db3d3b3b15',
            url: 'http://firstExample.com',
            language: {
                name: 'Deutsch',
            },
            currency: {
                name: 'Euro',
                translated: {
                    name: 'Euro',
                },
            },
            snippetSet: {
                name: 'BASE de-DE',
            },
            measurementUnits: {
                system: 'metric',
            },
            isNew: () => false,
        },
    ];
}

describe('src/module/sw-sales-channel/component/sw-sales-channel-detail-domains', () => {
    it('should have button and context menu item enabled', async () => {
        const wrapper = await createWrapper();

        const button = wrapper.find('.sw-sales-channel-detail__button-domain-add');
        expect(button.attributes().disabled).toBeUndefined();

        const contextMenuItems = wrapper.findAll('sw-context-menu-item-stub');
        contextMenuItems.forEach((item) => {
            expect(item.attributes().disabled).toBeUndefined();
        });
    });

    it('should have button and context menu item disabled', async () => {
        const wrapper = await createWrapper({
            disableEdit: true,
        });

        const button = wrapper.find('.sw-sales-channel-detail__button-domain-add');
        expect(button.attributes('disabled')).toBeDefined();

        const contextMenuItems = wrapper.findAll('sw-context-menu-item-stub');
        contextMenuItems.forEach((item) => {
            expect(item.attributes().disabled).toBe('true');
        });
    });

    it('should list all domains', async () => {
        const wrapper = await createWrapper({}, getExampleDomains());

        const rows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        expect(rows).toHaveLength(2);
    });

    it('should open the edit modal when clicking the domain url', async () => {
        const wrapper = await createWrapper({
            salesChannel: {
                languages: getExampleLanguages(),
                currencies: [],
                domains: getExampleDomains(),
            },
        });

        const link = wrapper.find('.sw-data-grid__body .sw-sales-channel-detail-domains__domain-url-link');
        expect(link.attributes('role')).toBe('button');

        await link.trigger('click');

        expect(wrapper.vm.isEditingDomain).toBe(true);
        expect(wrapper.vm.currentDomain.url).toBe(link.text());
    });

    it('should render the domain url as plain text when editing is disabled', async () => {
        const wrapper = await createWrapper({ disableEdit: true }, getExampleDomains());

        expect(wrapper.find('.sw-sales-channel-detail-domains__domain-url-link').exists()).toBe(false);
        expect(wrapper.find('.sw-data-grid__body .sw-data-grid__cell--url').text()).toBe(wrapper.vm.sortedDomains[0].url);
    });

    it('should sort all domains', async () => {
        const wrapper = await createWrapper({}, getExampleDomains());

        const rows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        const expectedRow = rows.at(0);
        expect(expectedRow.find('.sw-data-grid__cell--url .sw-data-grid__cell-content').text()).toBe(
            'http://firstExample.com',
        );
    });

    it('should sort all domains descending', async () => {
        const wrapper = await createWrapper({}, getExampleDomains());

        await wrapper.setData({
            sortDirection: 'DESC',
        });

        const rows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        const expectedRow = rows.at(0);
        expect(expectedRow.find('.sw-data-grid__cell--url .sw-data-grid__cell-content').text()).toBe(
            'http://secondExample.com',
        );
    });

    it('should properly natural sort', async () => {
        const domains = getExampleDomains();
        domains[0].url = 'http://0.0.0.2';
        domains[1].url = 'http://0.0.0.10';

        const wrapper = await createWrapper({}, domains);

        const rows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        const expectedRow = rows.at(0);
        expect(expectedRow.find('.sw-data-grid__cell--url .sw-data-grid__cell-content').text()).toBe('http://0.0.0.2');
    });

    it('should sort by currency', async () => {
        const wrapper = await createWrapper({}, getExampleDomains());

        await wrapper.setData({
            sortBy: 'currencyId',
        });

        const rows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        const expectedRow = rows.at(0);
        expect(expectedRow.find('.sw-data-grid__cell--url .sw-data-grid__cell-content').text()).toBe(
            'http://secondExample.com',
        );
    });

    it('should only display available languages', async () => {
        const languages = new EntityCollection('/languages', 'languages', Context.api, null, [
            {
                id: 'test1',
                name: 'language1',
            },
        ]);

        const wrapper = await createWrapper(
            {
                salesChannel: {
                    languages,
                    currencies: [],
                    domains: getExampleDomains(),
                },
            },
            getExampleDomains(),
        );

        wrapper.vm.onClickOpenCreateDomainModal();
        await wrapper.vm.$nextTick();

        expect(wrapper.getComponent('.sw-sales-channel-detail-domains__domain-language-select').vm.value).toBe(
            languages.first().id,
        );
        expect([
            ...wrapper.getComponent('.sw-sales-channel-detail-domains__domain-language-select').vm.$data.results,
        ]).toStrictEqual([...languages]);
    });

    it('should restrict selectable currencies to available currencies', async () => {
        const currencies = new EntityCollection('/currencies', 'currencies', Context.api, null, [
            {
                id: 'test1',
                name: 'currency1',
            },
        ]);

        const wrapper = await createWrapper(
            {
                salesChannel: {
                    languages: [],
                    currencies,
                    domains: getExampleDomains(),
                },
            },
            getExampleDomains(),
        );

        wrapper.vm.onClickOpenCreateDomainModal();
        await wrapper.vm.$nextTick();

        const currencySelect = wrapper.getComponent('.sw-sales-channel-detail-domains__domain-currency-select');
        const parsedCriteria = currencySelect.props('criteria').parse();

        expect(currencySelect.props('value')).toBe(currencies.first().id);
        expect(currencySelect.props('entity')).toBe('currency');
        expect(parsedCriteria.filter).toEqual([
            expect.objectContaining({
                type: 'equalsAny',
                field: 'id',
                value: currencies.first().id,
            }),
        ]);
    });

    it('should include the default currency in the selectable currencies', async () => {
        const wrapper = await createWrapper(
            {
                salesChannel: {
                    currencyId: 'default-currency-id',
                    languages: [],
                    currencies: new EntityCollection('/currencies', 'currencies', Context.api, null, []),
                    domains: getExampleDomains(),
                },
            },
            getExampleDomains(),
        );

        wrapper.vm.onClickOpenCreateDomainModal();
        await wrapper.vm.$nextTick();

        const currencySelect = wrapper.getComponent('.sw-sales-channel-detail-domains__domain-currency-select');
        const parsedCriteria = currencySelect.props('criteria').parse();

        expect(parsedCriteria.filter).toEqual([
            expect.objectContaining({
                type: 'equalsAny',
                field: 'id',
                value: 'default-currency-id',
            }),
        ]);
    });

    it('should preselect requested language and currency when opening the create modal', async () => {
        const languages = new EntityCollection('/languages', 'languages', Context.api, null, [
            {
                id: 'language-1',
                name: 'English',
            },
            {
                id: 'language-2',
                name: 'German',
            },
        ]);

        const currencies = new EntityCollection('/currencies', 'currencies', Context.api, null, [
            {
                id: 'currency-1',
                name: 'Euro',
            },
            {
                id: 'currency-2',
                name: 'US dollar',
            },
        ]);

        const wrapper = await createWrapper({
            salesChannel: {
                languages,
                currencies,
                domains: new EntityCollection('/sales-channel-domain', 'sales_channel_domain', Context.api, null, []),
            },
        });

        wrapper.vm.onClickOpenCreateDomainModal({
            languageId: 'language-2',
            currencyId: 'currency-2',
        });

        expect(wrapper.vm.currentDomain.languageId).toBe('language-2');
        expect(wrapper.vm.currentDomain.language).toStrictEqual(languages.get('language-2'));
        expect(wrapper.vm.currentDomain.currencyId).toBe('currency-2');
        expect(wrapper.vm.currentDomain.currency).toStrictEqual(currencies.get('currency-2'));
    });

    it('verifyUrl › returns false, if the url exists either locally, or in the database', async () => {
        const exampleDomains = getExampleDomains();
        const wrapper = await createWrapper({}, exampleDomains);
        let localResult = false;
        let dbResult = false;
        wrapper.vm.domainExistsLocal = jest.fn(() => localResult);
        wrapper.vm.domainExistsInDatabase = jest.fn(() => dbResult);

        expect(await wrapper.vm.verifyUrl(exampleDomains[0])).toBeTruthy();

        localResult = true;

        expect(await wrapper.vm.verifyUrl(exampleDomains[0])).toBeFalsy();

        localResult = false;
        dbResult = true;

        expect(await wrapper.vm.verifyUrl(exampleDomains[0])).toBeFalsy();
    });

    it('domainExistsLocal › checks if the given domains url already exists locally', async () => {
        const exampleDomains = getExampleDomains();
        const wrapper = await createWrapper({}, exampleDomains);
        const testedDomain = {
            id: '8a243080f92e4c719546314b577cf82b',
            url: 'http://foo.bar',
        };

        expect(wrapper.vm.domainExistsLocal(testedDomain)).toBeFalsy();
        expect(wrapper.vm.domainExistsLocal(exampleDomains[0])).toBeFalsy();

        testedDomain.url = exampleDomains[0].url;

        expect(wrapper.vm.domainExistsLocal(testedDomain)).toBeTruthy();
    });

    it('isOriginalUrl › checks if "url" equals the backup domains url', async () => {
        const exampleDomains = getExampleDomains();
        const testedDomain = exampleDomains[0];
        const wrapper = await createWrapper({}, exampleDomains);

        await wrapper.setData({ currentDomainBackup: exampleDomains[0] });
        expect(wrapper.vm.isOriginalUrl(testedDomain.url)).toBeTruthy();

        await wrapper.setData({ currentDomainBackup: exampleDomains[1] });
        expect(wrapper.vm.isOriginalUrl(testedDomain.url)).toBeFalsy();
    });

    it('onClickAddNewDomain › early returns, if a domain is saved with its original "url" value', async () => {
        const exampleDomains = getExampleDomains();
        const testedDomain = exampleDomains[0];
        const wrapper = await createWrapper(
            {
                salesChannel: {
                    languages: [],
                    domains: [],
                    currencies: [],
                },
            },
            exampleDomains,
        );

        wrapper.vm.isOriginalUrl = jest.fn(() => true);
        wrapper.vm.verifyUrl = jest.fn();
        await wrapper.setData({
            currentDomain: testedDomain,
            currentDomainBackup: testedDomain,
        });

        await wrapper.vm.onClickAddNewDomain();

        expect(wrapper.vm.verifyUrl).not.toHaveBeenCalled();
    });

    it('should delete a domain when onConfirmDeleteDomain is called', async () => {
        const domains = new EntityCollection('/sales-channel-domain', 'sales_channel_domain', Context.api, null, [
            {
                id: 'domain-1',
                url: 'http://firstExample.com',
                productExports: [],
                language: {
                    name: 'Deutsch',
                },
                currency: {
                    name: 'Euro',
                    translated: {
                        name: 'Euro',
                    },
                },
                measurementUnits: {
                    system: 'metric',
                },
                snippetSet: {
                    name: 'BASE de-DE',
                },
                isNew: () => false,
            },
        ]);

        const wrapper = await createWrapper(
            {
                salesChannel: {
                    languages: [],
                    currencies: [],
                    domains: domains,
                },
            },
            [],
        );

        const domainToDelete = domains.first();

        await wrapper.vm.onConfirmDeleteDomain(domainToDelete);

        const domainExists = wrapper.vm.salesChannel.domains.some((domain) => domain.id === domainToDelete.id);

        expect(domainExists).toBe(false);
    });

    it('should not delete a domain when onConfirmDeleteDomain is called and domain.productExports.length > 0', async () => {
        const domains = new EntityCollection('/sales-channel-domain', 'sales_channel_domain', Context.api, null, [
            {
                id: 'domain-1',
                url: 'http://firstExample.com',
                productExports: [{}],
                language: {
                    name: 'Deutsch',
                },
                currency: {
                    name: 'Euro',
                    translated: {
                        name: 'Euro',
                    },
                },
                measurementUnits: {
                    system: 'metric',
                },
                snippetSet: {
                    name: 'BASE de-DE',
                },
                isNew: () => false,
            },
        ]);

        const wrapper = await createWrapper(
            {
                salesChannel: {
                    languages: [],
                    currencies: [],
                    domains: domains,
                },
            },
            [],
        );

        const domainToDelete = domains.first();

        await wrapper.vm.onConfirmDeleteDomain(domainToDelete);

        const domainExists = wrapper.vm.salesChannel.domains.some((domain) => domain.id === domainToDelete.id);

        expect(domainExists).toBe(true);
    });

    it('should display name measurement system with translate', async () => {
        const domains = new EntityCollection('/sales-channel-domain', 'sales_channel_domain', Context.api, null, [
            {
                id: 'domain-1',
                url: 'http://firstExample.com',
                productExports: [{}],
                language: {
                    name: 'Deutsch',
                },
                currency: {
                    name: 'Euro',
                    translated: {
                        name: 'Euro',
                    },
                },
                measurementUnits: {
                    system: 'metric',
                },
                snippetSet: {
                    name: 'BASE de-DE',
                },
                isNew: () => false,
            },
        ]);

        const wrapper = await createWrapper(
            {
                salesChannel: {
                    languages: [],
                    currencies: [],
                    domains: domains,
                },
            },
            [],
        );
        await flushPromises();

        const rows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        const expectedRow = rows.at(0);
        expect(expectedRow.find('.sw-data-grid__cell--measurementSystemName .sw-data-grid__cell-content').text()).toBe(
            'Metric system',
        );
    });

    it('should set initial measurement unit when opening the create modal', async () => {
        const wrapper = await createWrapper({
            salesChannel: {
                domains: [],
                currencies: [],
                languages: [],
                measurementUnits: {
                    system: 'metric',
                    units: {
                        length: 'metre',
                        weight: 'kilogram',
                    },
                },
            },
        });

        wrapper.vm.onClickOpenCreateDomainModal();

        expect(wrapper.vm.currentDomain.measurementUnits).toEqual({
            system: 'metric',
            units: {
                length: 'metre',
                weight: 'kilogram',
            },
        });
    });

    it('should only show the external storefront checkbox for a headless sales channel', async () => {
        // the checkbox is gated on this computed via v-if in the template
        const headlessWrapper = await createWrapper({
            salesChannel: {
                typeId: Shopware.Defaults.apiSalesChannelTypeId,
                domains: [],
            },
        });
        expect(headlessWrapper.vm.salesChannelIsHeadless).toBe(true);

        const storefrontWrapper = await createWrapper({
            salesChannel: {
                typeId: 'some-storefront-type-id',
                domains: [],
            },
        });
        expect(storefrontWrapper.vm.salesChannelIsHeadless).toBe(false);
    });

    it('should group snippet sets matching the sales channel languages first', async () => {
        const wrapper = await createWrapper({
            salesChannel: {
                languages: getExampleLanguages(),
                currencies: [],
                domains: [],
            },
        });
        await flushPromises();

        wrapper.vm.onClickOpenCreateDomainModal();
        await wrapper.vm.$nextTick();

        const snippetSetSelect = wrapper.getComponent('.sw-sales-channel-detail-domains__domain-snippet-set-select');

        expect(snippetSetSelect.props('groups')).toEqual([
            { id: 'de', label: 'Deutsch' },
            { id: 'en', label: 'English' },
            { id: 'misc', label: 'sw-sales-channel.detail.snippetSetGroupOther' },
        ]);
        expect(snippetSetSelect.props('options').map((option) => [option.value, option.group])).toEqual([
            ['set-de-de', 'de'],
            ['set-de-du', 'de'],
            ['set-de-ch', 'de'],
            ['set-en-gb', 'en'],
            ['set-en-us', 'en'],
            ['set-es-es', 'misc'],
            ['set-fr-fr', 'misc'],
        ]);
    });

    it('should move the group of the selected domain language to the top', async () => {
        const wrapper = await createWrapper({
            salesChannel: {
                languages: getExampleLanguages(),
                currencies: [],
                domains: [],
            },
        });
        await flushPromises();

        wrapper.vm.onClickOpenCreateDomainModal();
        wrapper.vm.onSnippetSetSelect('set-de-ch');
        wrapper.vm.onLanguageSelect('language-en');
        await wrapper.vm.$nextTick();

        const snippetSetSelect = wrapper.getComponent('.sw-sales-channel-detail-domains__domain-snippet-set-select');

        expect(snippetSetSelect.props('groups').map((group) => group.id)).toEqual([
            'en',
            'de',
            'misc',
        ]);
        expect(snippetSetSelect.props('options').map((option) => option.value)).toEqual([
            'set-en-gb',
            'set-en-us',
            'set-de-de',
            'set-de-du',
            'set-de-ch',
            'set-es-es',
            'set-fr-fr',
        ]);
    });

    it('should preselect the snippet set matching the selected language', async () => {
        const wrapper = await createWrapper({
            salesChannel: {
                languages: getExampleLanguages(),
                currencies: [],
                domains: [],
            },
        });
        await flushPromises();

        wrapper.vm.onClickOpenCreateDomainModal();
        expect(wrapper.vm.currentDomain.snippetSetId).toBeUndefined();

        wrapper.vm.onLanguageSelect('language-en');

        expect(wrapper.vm.currentDomain.snippetSetId).toBe('set-en-gb');
        expect(wrapper.vm.currentDomain.snippetSet).toEqual(expect.objectContaining({ id: 'set-en-gb' }));
    });

    it('should write the language and preselect the snippet set when the language select emits a value', async () => {
        const wrapper = await createWrapper({
            salesChannel: {
                languages: getExampleLanguages(),
                currencies: [],
                domains: [],
            },
        });
        await flushPromises();

        wrapper.vm.onClickOpenCreateDomainModal();
        await wrapper.vm.$nextTick();

        wrapper
            .getComponent('.sw-sales-channel-detail-domains__domain-language-select')
            .vm.$emit('update:value', 'language-en');

        expect(wrapper.vm.currentDomain.languageId).toBe('language-en');
        expect(wrapper.vm.currentDomain.language).toEqual(expect.objectContaining({ id: 'language-en' }));
        expect(wrapper.vm.currentDomain.snippetSetId).toBe('set-en-gb');
    });

    it('should preselect the snippet set when opening the create modal with a language', async () => {
        const wrapper = await createWrapper({
            salesChannel: {
                languages: getExampleLanguages(),
                currencies: [],
                domains: [],
            },
        });
        await flushPromises();

        wrapper.vm.onClickOpenCreateDomainModal({ languageId: 'language-de' });

        expect(wrapper.vm.currentDomain.snippetSetId).toBe('set-de-de');
    });

    it('should preselect the snippet set once the data arrives after the create modal was opened', async () => {
        const wrapper = await createWrapper({
            salesChannel: {
                languages: getExampleLanguages(),
                currencies: [],
                domains: [],
            },
        });

        wrapper.vm.onClickOpenCreateDomainModal({ languageId: 'language-de' });
        expect(wrapper.vm.currentDomain.snippetSetId).toBeUndefined();

        await flushPromises();

        expect(wrapper.vm.currentDomain.snippetSetId).toBe('set-de-de');
    });

    it('should not override a chosen snippet set when the data arrives after the create modal was opened', async () => {
        const wrapper = await createWrapper({
            salesChannel: {
                languages: getExampleLanguages(),
                currencies: [],
                domains: [],
            },
        });

        wrapper.vm.onClickOpenCreateDomainModal({ languageId: 'language-de' });
        wrapper.vm.currentDomain.snippetSetId = 'set-fr-fr';

        await flushPromises();

        expect(wrapper.vm.currentDomain.snippetSetId).toBe('set-fr-fr');
    });

    it('should keep the selected snippet set when the language changes within the same agnostic locale', async () => {
        const wrapper = await createWrapper({
            salesChannel: {
                languages: getExampleLanguages(),
                currencies: [],
                domains: [],
            },
        });
        await flushPromises();

        wrapper.vm.onClickOpenCreateDomainModal();
        wrapper.vm.onSnippetSetSelect('set-de-ch');
        wrapper.vm.onLanguageSelect('language-de');

        expect(wrapper.vm.currentDomain.snippetSetId).toBe('set-de-ch');
        expect(wrapper.vm.currentDomain.snippetSet).toEqual(expect.objectContaining({ id: 'set-de-ch' }));
    });

    it('should swap the snippet set when the language changes to another agnostic locale', async () => {
        const wrapper = await createWrapper({
            salesChannel: {
                languages: getExampleLanguages(),
                currencies: [],
                domains: [],
            },
        });
        await flushPromises();

        wrapper.vm.onClickOpenCreateDomainModal();
        wrapper.vm.onSnippetSetSelect('set-de-ch');
        wrapper.vm.onLanguageSelect('language-en');

        expect(wrapper.vm.currentDomain.snippetSetId).toBe('set-en-gb');
        expect(wrapper.vm.currentDomain.snippetSet).toEqual(expect.objectContaining({ id: 'set-en-gb' }));
    });

    it('should fall back to the first snippet set of the agnostic locale without an exact match', async () => {
        const wrapper = await createWrapper({
            salesChannel: {
                languages: getExampleLanguages(),
                currencies: [],
                domains: [],
            },
        });
        await flushPromises();

        wrapper.vm.onClickOpenCreateDomainModal();
        wrapper.vm.onSnippetSetSelect('set-fr-fr');
        wrapper.vm.onLanguageSelect('language-en-au');

        expect(wrapper.vm.currentDomain.snippetSetId).toBe('set-en-gb');
    });

    it('should search snippet sets by name and iso', async () => {
        const wrapper = await createWrapper({
            salesChannel: {
                languages: getExampleLanguages(),
                currencies: [],
                domains: [],
            },
        });
        await flushPromises();

        const search = (searchTerm) =>
            wrapper.vm
                .snippetSetSearchFunction({
                    options: wrapper.vm.snippetSetOptions,
                    labelProperty: 'label',
                    searchTerm,
                })
                .map((option) => option.value);

        expect(search('de-ch')).toEqual(['set-de-ch']);
        expect(search('Deutsch')).toEqual(['set-de-du']);
        expect(search('de-DE')).toEqual([
            'set-de-de',
            'set-de-du',
        ]);
    });
});
