/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';

class MockRepositoryFactory {
    constructor() {
        this.data = [
            {
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
                salesChannel: {},
            },
        ];
    }

    search() {
        return new Promise((resolve) => {
            resolve({ first: () => this.data[0] });
        });
    }
}

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-newsletter-recipient-detail', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-page': {
                        template: '<div><slot name="smart-bar-actions"></slot><slot name="content">CONTENT</slot></div>',
                    },
                    'sw-entity-listing': await wrapTestComponent('sw-entity-listing', { sync: true }),
                    'sw-data-grid': await wrapTestComponent('sw-data-grid', {
                        sync: true,
                    }),
                    'sw-card-view': await wrapTestComponent('sw-card-view', {
                        sync: true,
                    }),
                    'sw-container': {
                        template: '<div class="sw-container"><slot></slot></div>',
                    },
                    'sw-entity-single-select': {
                        template: '<div class="sw-field"></div>',
                        props: ['disabled'],
                    },
                    'sw-text-field': {
                        template: '<div class="sw-field"></div>',
                        props: ['disabled'],
                    },
                    'sw-entity-tag-select': {
                        template: '<div class="sw-field"></div>',
                        props: ['disabled'],
                    },
                    'sw-button': {
                        template: '<div id="save-btn"></div>',
                        props: ['disabled'],
                    },
                    'sw-loader': true,
                    'sw-card': {
                        template: '<div><slot name="toolbar">TOOLBAR</slot><slot>CONTENT</slot></div>',
                    },
                    'sw-skeleton': true,
                    'sw-error-summary': true,
                    'sw-custom-field-set-renderer': true,
                },
                provide: {
                    stateStyleDataProviderService: {},
                    repositoryFactory: {
                        create: (type) => new MockRepositoryFactory(type),
                    },
                    customFieldDataProviderService: {
                        getCustomFieldSets: () => Promise.resolve([]),
                    },
                },
                mocks: {
                    $route: {
                        params: { id: '92618290af63445b973cc1021d60e3f5' },
                    },
                },
            },
            props: {
                manufacturerId: 'id',
            },
        },
    );
}

describe('src/module/sw-newsletter-recipient/page/sw-newsletter-recipient-detail/sw-newsletter-recipient-detail', () => {
    it('should disable all inputs and disallow saving', async () => {
        global.activeAclRoles = [];
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        // check if the save-action-btn is disabled
        expect(wrapper.getComponent('#save-btn').props('disabled')).toBe(true);

        const fields = wrapper.findAllComponents('.sw-field');
        expect(fields).toHaveLength(11);

        // check that they are all disabled
        expect(fields.every((field) => field.props('disabled'))).toBe(true);
    });

    it('should enable all inputs and allow saving', async () => {
        global.activeAclRoles = ['newsletter_recipient.editor'];
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        // check if the save-action-btn is enabled
        expect(wrapper.getComponent('#save-btn').props('disabled')).toBeFalsy();

        const fields = wrapper.findAllComponents('.sw-field');
        expect(fields).toHaveLength(11);

        // check that they are all enabled minus the saleschannel select which is always disabled
        expect(fields.filter((field) => !field.props('disabled'))).toHaveLength(10);

        // now check that the salechannel is disabled
        expect(wrapper.getComponent('[label="sw-newsletter-recipient.general.salesChannel"]').props('disabled')).toBe(true);
    });
});
