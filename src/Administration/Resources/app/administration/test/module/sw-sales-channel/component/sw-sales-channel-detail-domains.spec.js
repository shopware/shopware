import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-sales-channel/component/sw-sales-channel-detail-domains';
import 'src/app/component/data-grid/sw-data-grid';

function createWrapper(customProps = {}, domains = []) {
    return shallowMount(Shopware.Component.build('sw-sales-channel-detail-domains'), {
        stubs: {
            'sw-card': {
                template: '<div><slot></slot><slot name="grid"></slot></div>'
            },
            'sw-button': true,
            'sw-data-grid': Shopware.Component.build('sw-data-grid'),
            'sw-context-menu-item': true,
            'sw-icon': true,
            'sw-context-button': true
        },
        provide: {
            repositoryFactory: {}
        },
        propsData: {
            salesChannel: {
                domains: domains
            },
            ...customProps
        }
    });
}

function getExampleDomains() {
    return [
        {
            url: 'http://secondExample.com',
            language: {
                name: 'Deutsch'
            },
            currency: {
                name: 'Danish krone',
                translated: {
                    name: 'Danish krone'
                }
            },
            snippetSet: {
                name: 'BASE de-DE'
            }
        },
        {
            url: 'http://firstExample.com',
            language: {
                name: 'Deutsch'
            },
            currency: {
                name: 'Euro',
                translated: {
                    name: 'Euro'
                }
            },
            snippetSet: {
                name: 'BASE de-DE'
            }
        }
    ];
}

describe('src/module/sw-sales-channel/component/sw-sales-channel-detail-domains', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should have button enabled', async () => {
        const wrapper = createWrapper();

        const button = wrapper.find('.sw-sales-channel-detail__button-domain-add');

        expect(button.attributes().disabled).toBeUndefined();
    });

    it('should have button disabled', async () => {
        const wrapper = createWrapper({
            disableEdit: true
        });

        const button = wrapper.find('.sw-sales-channel-detail__button-domain-add');

        expect(button.attributes().disabled).toBe('true');
    });

    it('should have context menu item enabled', async () => {
        const wrapper = createWrapper();

        const contextMenuItems = wrapper.findAll('sw-context-menu-item-stub');

        contextMenuItems.wrappers.forEach(item => {
            expect(item.attributes().disabled).toBeUndefined();
        });
    });

    it('should have context menu item disabled', async () => {
        const wrapper = createWrapper({
            disableEdit: true
        });

        const contextMenuItems = wrapper.findAll('sw-context-menu-item-stub');

        contextMenuItems.wrappers.forEach(item => {
            expect(item.attributes().disabled).toBe('true');
        });
    });

    it('should list all domains', async () => {
        const wrapper = createWrapper({}, getExampleDomains());

        const rows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        expect(rows.length).toBe(2);
    });

    it('should sort all domains', async () => {
        const wrapper = createWrapper({}, getExampleDomains());

        const rows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        const expectedRow = rows.at(0);
        expect(expectedRow.find('.sw-data-grid__cell--url .sw-data-grid__cell-content').text()).toBe('http://firstExample.com');
    });

    it('should sort all domains descending', async () => {
        const wrapper = createWrapper({}, getExampleDomains());

        await wrapper.setData({
            sortDirection: 'DESC'
        });

        const rows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        const expectedRow = rows.at(0);
        expect(expectedRow.find('.sw-data-grid__cell--url .sw-data-grid__cell-content').text()).toBe('http://secondExample.com');
    });

    it('should properly natural sort', async () => {
        const domains = getExampleDomains();
        domains[0].url = 'http://0.0.0.2';
        domains[1].url = 'http://0.0.0.10';

        const wrapper = createWrapper({}, domains);

        const rows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        const expectedRow = rows.at(0);
        expect(expectedRow.find('.sw-data-grid__cell--url .sw-data-grid__cell-content').text()).toBe('http://0.0.0.2');
    });

    it('should sort by currency', async () => {
        const wrapper = createWrapper({}, getExampleDomains());

        await wrapper.setData({
            sortBy: 'currencyId'
        });

        const rows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        const expectedRow = rows.at(0);
        expect(expectedRow.find('.sw-data-grid__cell--url .sw-data-grid__cell-content').text()).toBe('http://secondExample.com');
    });
});
