/**
 * @sw-package after-sales
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
        await flushPromises();

        // check if the save-action-btn is disabled
        expect(
            wrapper.findByText('button', 'sw-newsletter-recipient.general.buttonSave').attributes('disabled'),
        ).toBeDefined();

        const mtFields = wrapper.findAllComponents('.mt-field');
        const swFields = wrapper.findAllComponents('.sw-field');
        expect(mtFields.length + swFields.length).toBe(11);

        // check that they are all disabled
        expect(mtFields.every((field) => field.props('disabled'))).toBe(true);
        expect(swFields.every((field) => field.props('disabled'))).toBe(true);
    });

    it('should enable all inputs and allow saving', async () => {
        global.activeAclRoles = ['newsletter_recipient.editor'];
        const wrapper = await createWrapper();
        await flushPromises();

        // check if the save-action-btn is enabled
        expect(
            wrapper.findByText('button', 'sw-newsletter-recipient.general.buttonSave').attributes('disabled'),
        ).toBeUndefined();

        const mtFields = wrapper.findAllComponents('.mt-field');
        const swFields = wrapper.findAllComponents('.sw-field');
        expect(mtFields.length + swFields.length).toBe(11);

        /* eslint-disable jest/prefer-to-have-length */
        // check that they are all enabled minus the saleschannel select which is always disabled
        expect(mtFields.filter((field) => !field.props('disabled')).length).toBe(7);
        expect(swFields.filter((field) => !field.props('disabled')).length).toBe(3);
        /* eslint-enable jest/prefer-to-have-length */

        // now check that the salechannel is disabled
        expect(wrapper.getComponent('[label="sw-newsletter-recipient.general.salesChannel"]').props('disabled')).toBe(true);
    });
});
