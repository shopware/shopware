import { mount } from '@vue/test-utils_v3';
import FilterService from 'src/app/service/filter.service';

async function createWrapper(privileges = []) {
    return mount(await wrapTestComponent('sw-settings-rule-list', { sync: true }), {
        global: {
            stubs: {
                'sw-page': {
                    template: `
    <div>
        <slot name="smart-bar-actions"></slot>
        <slot name="content"></slot>
    </div>`,
                },
                'sw-button': true,
                'sw-empty-state': true,
                'sw-loader': true,
                'sw-entity-listing': {
                    template: `
    <div class="sw-entity-listing">
        <slot name="more-actions"></slot>
    </div>
    `,
                },
                'sw-context-menu-item': await wrapTestComponent('sw-context-menu-item'),
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        search: () => Promise.resolve([]),
                    }),
                },
                filterFactory: {
                    create: (name, filters) => filters,
                },
                filterService: new FilterService({
                    userConfigRepository: {
                        search: () => Promise.resolve({ length: 0 }),
                        create: () => ({}),
                    },
                }),
                ruleConditionDataProviderService: {
                    getConditions: () => {
                        return [{ type: 'foo', label: 'bar' }];
                    },
                    getGroups: () => {
                        return [{ id: 'foo', name: 'bar' }];
                    },
                    getByGroup: () => {
                        return [{ type: 'foo' }];
                    },
                },
                acl: {
                    can: (identifier) => {
                        if (!identifier) {
                            return true;
                        }

                        return privileges.includes(identifier);
                    },
                },
                searchRankingService: {},
            },
            mocks: {
                $route: {
                    query: 'foo',
                },
            },
        },
    });
}

describe('src/module/sw-settings-rule/page/sw-settings-rule-list', () => {
    beforeEach(() => {
        Shopware.Application.view.router = {
            currentRoute: {
                query: '',
            },
            push: () => {},
        };
    });

    it('should have disabled fields', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const buttonAddRule = wrapper.get('sw-button-stub');
        const entityListing = wrapper.get('.sw-entity-listing');
        const contextMenuItemDuplicate = wrapper.get('.sw-context-menu-item');

        expect(buttonAddRule.attributes().disabled).toBe('true');
        expect(entityListing.attributes()['show-selection']).toBeUndefined();
        expect(entityListing.attributes()['allow-edit']).toBeUndefined();
        expect(entityListing.attributes()['allow-delete']).toBeUndefined();
        expect(contextMenuItemDuplicate.attributes().class).toContain('is--disabled');
    });

    it('should have enabled fields for creator', async () => {
        const wrapper = await createWrapper([
            'rule.creator',
        ]);
        await flushPromises();

        const buttonAddRule = wrapper.find('sw-button-stub');
        const entityListing = wrapper.find('.sw-entity-listing');
        const contextMenuItemDuplicate = wrapper.find('.sw-context-menu-item');

        expect(buttonAddRule.attributes().disabled).toBeUndefined();
        expect(entityListing.attributes()['show-selection']).toBeUndefined();
        expect(entityListing.attributes()['allow-edit']).toBeUndefined();
        expect(entityListing.attributes()['allow-delete']).toBeUndefined();
        expect(contextMenuItemDuplicate.attributes().class).not.toContain('is--disabled');
    });

    it('only should have enabled fields for editor', async () => {
        const wrapper = await createWrapper([
            'rule.editor',
        ]);
        await flushPromises();

        const buttonAddRule = wrapper.find('sw-button-stub');
        const entityListing = wrapper.find('.sw-entity-listing');
        const contextMenuItemDuplicate = wrapper.find('.sw-context-menu-item');

        expect(buttonAddRule.attributes().disabled).toBe('true');
        expect(entityListing.attributes()['show-selection']).toBeUndefined();
        expect(entityListing.attributes()['allow-edit']).toBe('true');
        expect(entityListing.attributes()['allow-delete']).toBeUndefined();
        expect(contextMenuItemDuplicate.attributes().class).toContain('is--disabled');
    });

    it('should have enabled fields for deleter', async () => {
        const wrapper = await createWrapper([
            'rule.deleter',
        ]);
        await flushPromises();

        const buttonAddRule = wrapper.find('sw-button-stub');
        const entityListing = wrapper.find('.sw-entity-listing');
        const contextMenuItemDuplicate = wrapper.find('.sw-context-menu-item');

        expect(buttonAddRule.attributes().disabled).toBe('true');
        expect(entityListing.attributes()['show-selection']).toBe('true');
        expect(entityListing.attributes()['allow-edit']).toBeUndefined();
        expect(entityListing.attributes()['allow-delete']).toBe('true');
        expect(contextMenuItemDuplicate.attributes().class).toContain('is--disabled');
    });

    it('should duplicate a rule and should overwrite name and createdAt values', async () => {
        const wrapper = await createWrapper(['rule.creator']);
        wrapper.vm.onDuplicate = jest.fn();
        wrapper.vm.onDuplicate.mockReturnValueOnce('hi');

        await flushPromises();

        const contextMenuItemDuplicate = wrapper.find('.sw-context-menu-item');
        await contextMenuItemDuplicate.trigger('click');

        expect(wrapper.vm.onDuplicate)
            .toHaveBeenCalledTimes(1);
    });

    it('should get filter options for conditions', async () => {
        const wrapper = await createWrapper(['rule.creator']);
        const conditionFilterOptions = wrapper.vm.conditionFilterOptions;

        expect(conditionFilterOptions).toEqual([{ label: 'bar', value: 'foo' }]);
    });

    it('should get filter options for groups', async () => {
        const wrapper = await createWrapper(['rule.creator']);
        const groupFilterOptions = wrapper.vm.groupFilterOptions;

        expect(groupFilterOptions).toEqual([{ label: 'bar', value: 'foo' }]);
    });

    it('should get filter options for associations', async () => {
        const wrapper = await createWrapper(['rule.creator']);
        const associationFilterOptions = wrapper.vm.associationFilterOptions;

        expect(associationFilterOptions.map(option => option.value)).toContain('productPrices');
        expect(associationFilterOptions.map(option => option.value)).toContain('paymentMethods');
    });

    it('should get list filters', async () => {
        const wrapper = await createWrapper(['rule.creator']);
        const listFilters = wrapper.vm.listFilters;

        expect(Object.keys(listFilters)).toContain('conditionGroups');
        expect(Object.keys(listFilters)).toContain('conditions');
        expect(Object.keys(listFilters)).toContain('assignments');
        expect(Object.keys(listFilters)).toContain('tags');
    });

    it('should get counts', async () => {
        const wrapper = await createWrapper(['rule.creator']);

        await wrapper.setData({
            rules: {
                aggregations: {
                    productPrices: {
                        buckets: [{
                            key: '1',
                            productPrices: {
                                count: 100,
                            },
                        }],
                    },
                },
            },
        });

        expect(wrapper.vm.getCounts('productPrices', '1')).toBe(100);
    });

    it('should return filters from filter registry', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.dateFilter).toEqual(expect.any(Function));
    });
});
