import { createLocalVue, mount } from '@vue/test-utils';
import 'src/module/sw-newsletter-recipient/page/sw-newsletter-recipient-detail';

import 'src/app/component/entity/sw-entity-listing';
import 'src/app/component/data-grid/sw-data-grid';
import 'src/app/component/structure/sw-card-view';

class MockRepositoryFactory {
    constructor() {
        this.data = [{
            email: 'test@example.com',
            title: null,
            firstName: 'Max',
            lastName: 'Mustermann',
            zipCode: '48624',
            city: 'Schöppingen',
            street: null,
            status: 'direct',
            hash: 'c225f2cc023946679c4e0d9189375402',
            confirmedAt: null,
            salutationId: 'fd04f0ca555143ab9f28294699f7384b',
            languageId: '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
            salesChannelId: '7b872c384b254613b5a4bd5c8b965bab',
            createdAt: '2020-09-23T11:42:12.104+00:00',
            updatedAt: '2020-09-23T13:27:01.436+00:00',
            apiAlias: null,
            id: '92618290af63445b973cc1021d60e3f5',
            salesChannel: {}
        }];
    }

    search() {
        return new Promise(resolve => resolve({ first: () => this.data[0] }));
    }
}

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return mount(Shopware.Component.build('sw-newsletter-recipient-detail'), {
        localVue,
        data() {
            return {
                newsletterRecipient: null,
                salutations: [],
                languages: [],
                salesChannels: [],
                isLoading: false
            };
        },
        stubs: {
            'sw-page': {
                template: '<div><slot name="smart-bar-actions"></slot><slot name="content">CONTENT</slot></div>'
            },
            'sw-entity-listing': Shopware.Component.build('sw-entity-listing'),
            'sw-data-grid': Shopware.Component.build('sw-data-grid'),
            'sw-card-view': Shopware.Component.build('sw-card-view'),
            'sw-container': { template: '<div class="sw-container"><slot></slot></div>' },
            'sw-entity-single-select': {
                template: '<div class="sw-field"></div>',
                props: ['disabled']
            },
            'sw-field': {
                template: '<div class="sw-field"></div>',
                props: ['disabled']
            },
            'sw-entity-tag-select': {
                template: '<div class="sw-field"></div>',
                props: ['disabled']
            },
            'sw-button': {
                template: '<div id="save-btn"></div>',
                props: ['disabled']
            },
            'sw-loader': true,
            'sw-card': {
                template: '<div><slot name="toolbar">TOOLBAR</slot><slot>CONTENT</slot></div>'
            }
        },
        provide: {
            acl: {
                can: key => (key ? privileges.includes(key) : true)
            },
            stateStyleDataProviderService: {},
            repositoryFactory: {
                create: (type) => new MockRepositoryFactory(type)
            }
        },
        mocks: {
            $tc: v => v,
            $route: { params: { id: '92618290af63445b973cc1021d60e3f5' } },
            $router: {
                replace: () => {
                }
            },
            $device: {
                getSystemKey: () => {
                },
                onResize: () => {}
            }
        },
        propsData: {
            manufacturerId: 'id'
        }
    });
}

describe('src/module/sw-manufacturer/page/sw-manufacturer-detail', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should disable all inputs and disallow saving', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        // check if the save-action-btn is disabled
        expect(wrapper.get('#save-btn').props().disabled).toBe(true);

        const fields = wrapper.findAll('.sw-field');
        expect(fields.length).toEqual(11);

        // check that they are all disabled
        expect(fields.wrappers.every(field => field.props().disabled)).toEqual(true);
    });


    it('should enable all inputs and allow saving', async () => {
        const wrapper = createWrapper(['newsletter_recipient.editor']);
        await wrapper.vm.$nextTick();

        // check if the save-action-btn is enabled
        expect(wrapper.get('#save-btn').props().disabled).toBeFalsy();

        const fields = wrapper.findAll('.sw-field');
        expect(fields.length).toEqual(11);

        // check that they are all enabled minus the saleschannel select which is always disabled
        expect(fields.wrappers.filter(field => !field.props().disabled).length).toEqual(10);

        // now check that the salechannel is disabled
        expect(wrapper.get('[label="sw-newsletter-recipient.general.salesChannel"]').props().disabled).toBe(true);
    });
});
