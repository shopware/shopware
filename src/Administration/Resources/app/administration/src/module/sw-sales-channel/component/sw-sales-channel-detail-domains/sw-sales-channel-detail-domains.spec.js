/**
 * @package buyers-experience
 */

import { mount } from '@vue/test-utils';

const { Context } = Shopware;
const { EntityCollection } = Shopware.Data;

async function createWrapper(customProps = {}, domains = []) {
    return mount(await wrapTestComponent('sw-sales-channel-detail-domains', { sync: true }), {
        global: {

            stubs: {
                'sw-card': {
                    template: '<div><slot></slot><slot name="grid"></slot></div>',
                },
                'sw-button': true,
                'sw-data-grid': await wrapTestComponent('sw-data-grid', { sync: true }),
                'sw-context-menu-item': true,
                'sw-icon': true,
                'sw-context-button': true,
                'sw-modal': await wrapTestComponent('sw-modal', { sync: true }),
                'sw-entity-single-select': true,
                'sw-radio-field': true,
                'sw-single-select': await wrapTestComponent('sw-single-select', { sync: true }),
                'sw-container': { template: '<div class="sw-container"><slot></slot></div>' },
                'sw-url-field': true,
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
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        create: () => {
                            return {
                                id: '44de136acf314e7184401d36406c1e90',
                                isNew: () => true,
                            };
                        },
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
    });
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
        contextMenuItems.forEach(item => {
            expect(item.attributes().disabled).toBeUndefined();
        });
    });

    it('should have button and context menu item disabled', async () => {
        const wrapper = await createWrapper({
            disableEdit: true,
        });

        const button = wrapper.find('.sw-sales-channel-detail__button-domain-add');
        expect(button.attributes().disabled).toBe('true');

        const contextMenuItems = wrapper.findAll('sw-context-menu-item-stub');
        contextMenuItems.forEach(item => {
            expect(item.attributes().disabled).toBe('true');
        });
    });

    it('should list all domains', async () => {
        const wrapper = await createWrapper({}, getExampleDomains());

        const rows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        expect(rows).toHaveLength(2);
    });

    it('should sort all domains', async () => {
        const wrapper = await createWrapper({}, getExampleDomains());

        const rows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        const expectedRow = rows.at(0);
        expect(expectedRow.find('.sw-data-grid__cell--url .sw-data-grid__cell-content').text()).toBe('http://firstExample.com');
    });

    it('should sort all domains descending', async () => {
        const wrapper = await createWrapper({}, getExampleDomains());

        await wrapper.setData({
            sortDirection: 'DESC',
        });

        const rows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        const expectedRow = rows.at(0);
        expect(expectedRow.find('.sw-data-grid__cell--url .sw-data-grid__cell-content').text()).toBe('http://secondExample.com');
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
        expect(expectedRow.find('.sw-data-grid__cell--url .sw-data-grid__cell-content').text()).toBe('http://secondExample.com');
    });

    it('should only display available languages', async () => {
        const languages = new EntityCollection(
            '/languages',
            'languages',
            Context.api,
            null,
            [{
                id: 'test1',
                name: 'language1',
            }],
        );

        const wrapper = await createWrapper({
            salesChannel: {
                languages,
                currencies: [],
                domains: getExampleDomains(),
            },
        }, getExampleDomains());

        wrapper.vm.onClickOpenCreateDomainModal();
        await wrapper.vm.$nextTick();

        expect(wrapper.getComponent('.sw-sales-channel-detail-domains__domain-language-select').vm.value).toBe(languages.first().id);
        expect(wrapper.getComponent('.sw-sales-channel-detail-domains__domain-language-select').vm.$data.results).toStrictEqual(languages);
    });

    it('should only display available currencies', async () => {
        const currencies = new EntityCollection(
            '/currencies',
            'currencies',
            Context.api,
            null,
            [{
                id: 'test1',
                name: 'currency1',
            }],
        );

        const wrapper = await createWrapper({
            salesChannel: {
                languages: [],
                currencies,
                domains: getExampleDomains(),
            },
        }, getExampleDomains());

        wrapper.vm.onClickOpenCreateDomainModal();
        await wrapper.vm.$nextTick();

        expect(wrapper.getComponent('.sw-sales-channel-detail-domains__domain-currency-select').vm.value).toBe(currencies.first().id);
        expect(wrapper.getComponent('.sw-sales-channel-detail-domains__domain-currency-select').vm.$data.results).toStrictEqual(currencies);
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
        const testedDomain = { id: '8a243080f92e4c719546314b577cf82b', url: 'http://foo.bar' };

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
        const wrapper = await createWrapper({
            salesChannel: {
                languages: [],
                domains: [],
                currencies: [],
            },
        }, exampleDomains);

        wrapper.vm.isOriginalUrl = jest.fn(() => true);
        wrapper.vm.verifyUrl = jest.fn();
        await wrapper.setData({ currentDomain: testedDomain, currentDomainBackup: testedDomain });

        await wrapper.vm.onClickAddNewDomain();

        expect(wrapper.vm.verifyUrl).not.toHaveBeenCalled();
    });

    it('should delete a domain when onConfirmDeleteDomain is called', async () => {
        const domains = new EntityCollection(
            '/sales-channel-domain',
            'sales_channel_domain',
            Context.api,
            null,
            [
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
                    snippetSet: {
                        name: 'BASE de-DE',
                    },
                    isNew: () => false,
                },
            ],
        );

        const wrapper = await createWrapper({
            salesChannel: {
                languages: [],
                currencies: [],
                domains: domains,
            },
        }, []);

        const domainToDelete = domains.first();

        await wrapper.vm.onConfirmDeleteDomain(domainToDelete);

        const domainExists = wrapper.vm.salesChannel.domains.some(domain => domain.id === domainToDelete.id);

        expect(domainExists).toBe(false);
    });

    it('should not delete a domain when onConfirmDeleteDomain is called and domain.productExports.length > 0', async () => {
        const domains = new EntityCollection(
            '/sales-channel-domain',
            'sales_channel_domain',
            Context.api,
            null,
            [
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
                    snippetSet: {
                        name: 'BASE de-DE',
                    },
                    isNew: () => false,
                },
            ],
        );

        const wrapper = await createWrapper({
            salesChannel: {
                languages: [],
                currencies: [],
                domains: domains,
            },
        }, []);

        const domainToDelete = domains.first();

        await wrapper.vm.onConfirmDeleteDomain(domainToDelete);

        const domainExists = wrapper.vm.salesChannel.domains.some(domain => domain.id === domainToDelete.id);

        expect(domainExists).toBe(true);
    });
});
