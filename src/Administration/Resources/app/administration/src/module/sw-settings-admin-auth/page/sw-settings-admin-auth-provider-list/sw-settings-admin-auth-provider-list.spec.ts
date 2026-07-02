/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

const providers = [
    { id: 'provider-one', name: 'Okta', active: true, priority: 10 },
    { id: 'provider-two', name: 'Keycloak', active: false, priority: 5 },
];
Object.defineProperty(providers, 'total', { value: providers.length });

async function createWrapper({ privileges = ['admin_auth.viewer'], searchMock = undefined } = {}) {
    const search = searchMock ?? jest.fn(() => Promise.resolve(providers));

    const wrapper = mount(await wrapTestComponent('sw-settings-admin-auth-provider-list', { sync: true }), {
        global: {
            mocks: {
                $router: { push: jest.fn() },
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        search,
                    }),
                },
                acl: {
                    can: (identifier: string) => {
                        if (!identifier) {
                            return true;
                        }

                        return privileges.includes(identifier);
                    },
                },
            },
            stubs: {
                'sw-page': {
                    template: `
                        <div class="sw-page">
                            <slot name="smart-bar-header"></slot>
                            <slot name="smart-bar-actions"></slot>
                            <slot name="content"></slot>
                            <slot></slot>
                        </div>
                    `,
                },
                'sw-tabs': {
                    template: '<div class="sw-tabs"><slot></slot></div>',
                },
                'sw-tabs-item': true,
                'sw-empty-state': true,
                'sw-entity-listing': {
                    props: [
                        'dataSource',
                        'columns',
                    ],
                    template: '<div class="sw-entity-listing"></div>',
                },
            },
        },
    });

    await flushPromises();

    return { wrapper, search };
}

describe('module/sw-settings-admin-auth/page/sw-settings-admin-auth-provider-list', () => {
    afterEach(() => {
        Shopware.Store.get('context').app.config.settings = undefined;
    });

    it('should define the name, active and priority columns', async () => {
        const { wrapper } = await createWrapper();

        const columns = wrapper.vm.columns as { property: string }[];

        expect(columns.map((column) => column.property)).toEqual([
            'name',
            'active',
            'priority',
        ]);
    });

    it('should load the providers sorted by priority and pass them to the listing', async () => {
        const { wrapper, search } = await createWrapper();

        expect(search).toHaveBeenCalledTimes(1);

        const criteria = (search.mock.calls[0] as unknown[])[0] as InstanceType<typeof Shopware.Data.Criteria>;
        expect(criteria.sortings).toEqual([
            expect.objectContaining({ field: 'priority', order: 'DESC' }),
        ]);

        const listing = wrapper.getComponent('.sw-entity-listing') as unknown as {
            props: (name: string) => unknown;
        };
        expect(listing.props('dataSource')).toEqual(providers);
    });

    it('should disable the create button without the creator privilege', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.get('.sw-settings-admin-auth-provider-list__create-action').attributes('disabled')).toBeDefined();

        const { wrapper: creatorWrapper } = await createWrapper({
            privileges: [
                'admin_auth.viewer',
                'admin_auth.creator',
            ],
        });

        expect(
            creatorWrapper.get('.sw-settings-admin-auth-provider-list__create-action').attributes('disabled'),
        ).toBeUndefined();
    });

    it('should show the managed banner instead of the listing when providers come from the YAML config', async () => {
        const appConfig = Shopware.Store.get('context').app.config;
        appConfig.settings = {
            adminAuth: {
                managedByConfig: true,
                adminUiDisabled: false,
            },
        } as unknown as typeof appConfig.settings;

        const { wrapper, search } = await createWrapper();

        expect(wrapper.find('.sw-settings-admin-auth-provider-list__managed-banner').exists()).toBe(true);
        expect(wrapper.find('.sw-entity-listing').exists()).toBe(false);
        expect(wrapper.find('.sw-settings-admin-auth-provider-list__create-action').exists()).toBe(false);
        expect(search).not.toHaveBeenCalled();
    });
});
