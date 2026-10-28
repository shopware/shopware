/**
 * @sw-package after-sales
 */
import { mount } from '@vue/test-utils';
import { reactive } from 'vue';

const recipientMock = [
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

async function createWrapper(route = { params: { id: '92618290af63445b973cc1021d60e3f5' } }, search = null) {
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
                    'mt-card': {
                        template: '<div><slot name="toolbar">TOOLBAR</slot><slot>CONTENT</slot></div>',
                    },
                    'sw-skeleton': true,
                    'sw-error-summary': true,
                    'sw-custom-field-set-renderer': true,
                    'sw-button-process': true,
                },
                provide: {
                    stateStyleDataProviderService: {},
                    repositoryFactory: {
                        create: () => ({
                            search: search ?? (() => Promise.resolve({ first: () => recipientMock[0] })),
                            save: () => Promise.resolve(),
                        }),
                    },
                    customFieldDataProviderService: {
                        getCustomFieldSets: () => Promise.resolve([]),
                    },
                },
                mocks: {
                    $route: route,
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

        expect(wrapper.find('sw-button-process-stub').attributes('disabled')).toBe('true');

        const mtFields = wrapper.findAllComponents('.mt-field');
        const swFields = wrapper.findAllComponents('.sw-field');
        expect(mtFields.length + swFields.length).toBe(11);

        expect(mtFields.every((field) => field.props('disabled'))).toBe(true);
        expect(swFields.every((field) => field.props('disabled'))).toBe(true);
    });

    it('should enable all inputs and allow saving', async () => {
        global.activeAclRoles = ['newsletter_recipient.editor'];
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('sw-button-process-stub').attributes('disabled')).toBe('false');

        const mtFields = wrapper.findAllComponents('.mt-field');
        const swFields = wrapper.findAllComponents('.sw-field');
        expect(mtFields.length + swFields.length).toBe(11);

        expect(mtFields.filter((field) => !field.props('disabled'))).toHaveLength(7);
        expect(swFields.filter((field) => !field.props('disabled'))).toHaveLength(3);

        expect(wrapper.getComponent('[label="sw-newsletter-recipient.general.salesChannel"]').props('disabled')).toBe(true);
    });

    it('should render loading indicator on save', async () => {
        global.activeAclRoles = ['newsletter_recipient.editor'];
        const wrapper = await createWrapper();
        await flushPromises();

        const saveButton = wrapper.find('sw-button-process-stub');
        expect(saveButton.attributes('disabled')).toBe('false');

        await saveButton.trigger('click');
        expect(saveButton.attributes('is-loading')).toBe('true');
    });

    it('should load the new recipient when the route id changes', async () => {
        global.activeAclRoles = ['newsletter_recipient.editor'];
        const route = reactive({ params: { id: 'first-id' } });
        const search = jest.fn((criteria) => {
            const id = criteria.filters.find((filter) => filter.field === 'id').value;

            return Promise.resolve({ first: () => ({ ...recipientMock[0], id, email: `${id}@example.com` }) });
        });
        const wrapper = await createWrapper(route, search);
        await flushPromises();

        const emailValue = () => wrapper.find('input[aria-label="sw-newsletter-recipient.list.email"]').element.value;
        const searchedId = (call) => search.mock.calls[call][0].filters.find((filter) => filter.field === 'id').value;

        expect(searchedId(0)).toBe('first-id');
        expect(emailValue()).toBe('first-id@example.com');

        route.params.id = 'second-id';
        await flushPromises();

        expect(search).toHaveBeenCalledTimes(2);
        expect(searchedId(1)).toBe('second-id');
        expect(emailValue()).toBe('second-id@example.com');
    });
});
