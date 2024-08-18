import { mount } from '@vue/test-utils';
import FilterService from 'src/app/service/filter.service';

const { Criteria } = Shopware.Data;

/**
 * @package services-settings
 * @group disabledCompat
 */

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
                'sw-search-bar': true,
                'sw-icon': true,
                'sw-language-switch': true,
                'sw-label': true,
                'sw-sidebar-item': true,
                'sw-sidebar-filter-panel': true,
                'sw-sidebar': true,
                'router-link': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        search: () => Promise.resolve([]),
                        clone: (id) => Promise.resolve({ id }),
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
                value: {
                    query: '',
                },
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
        await flushPromises();

        const ruleToDuplicate = {
            id: 'ruleId',
            name: 'ruleToDuplicate',
        };

        await wrapper.vm.onDuplicate(ruleToDuplicate);
        expect(wrapper.vm.$router.push).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.$router.push).toHaveBeenCalledWith({
            name: 'sw.settings.rule.detail',
            params: {
                id: ruleToDuplicate.id,
            },
        });
    });

    it('should get filter options for conditions', async () => {
        const wrapper = await createWrapper(['rule.creator']);
        await flushPromises();
        const conditionFilterOptions = wrapper.vm.conditionFilterOptions;

        expect(conditionFilterOptions).toEqual([{ label: 'bar', value: 'foo' }]);
    });

    it('should get filter options for groups', async () => {
        const wrapper = await createWrapper(['rule.creator']);
        await flushPromises();
        const groupFilterOptions = wrapper.vm.groupFilterOptions;

        expect(groupFilterOptions).toEqual([{ label: 'bar', value: 'foo' }]);
    });

    it('should get filter options for associations', async () => {
        const wrapper = await createWrapper(['rule.creator']);
        await flushPromises();
        const associationFilterOptions = wrapper.vm.associationFilterOptions;

        expect(associationFilterOptions.map(option => option.value)).toContain('productPrices');
        expect(associationFilterOptions.map(option => option.value)).toContain('paymentMethods');
    });

    it('should get list filters', async () => {
        const wrapper = await createWrapper(['rule.creator']);
        await flushPromises();
        const listFilters = wrapper.vm.listFilters;

        expect(Object.keys(listFilters)).toContain('conditionGroups');
        expect(Object.keys(listFilters)).toContain('conditions');
        expect(Object.keys(listFilters)).toContain('assignments');
        expect(Object.keys(listFilters)).toContain('tags');
    });

    it('should get counts', async () => {
        const wrapper = await createWrapper(['rule.creator']);
        await flushPromises();

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
        expect(wrapper.vm.getCounts('productPrices', '2')).toBe(0);
    });

    it('should return filters from filter registry', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.dateFilter).toEqual(expect.any(Function));
    });

    it('should consider criteria filters via updateCriteria (triggered by sw-sidebar-filter-panel)', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const filter = Criteria.equals('foo', 'bar');
        wrapper.vm.updateCriteria([filter]);
        await flushPromises();

        expect(wrapper.vm.listCriteria.filters).toContainEqual(filter);
    });

    it('should return a meta title', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.$createTitle = jest.fn(() => 'Title');
        const metaInfo = wrapper.vm.$options.metaInfo.call(wrapper.vm);

        expect(metaInfo.title).toBe('Title');
        expect(wrapper.vm.$createTitle).toHaveBeenNthCalledWith(1);
    });

    it('should consider assignmentProperties when it contains the sortBy property', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const assignemntProperties = wrapper.vm.assignmentProperties;
        expect(assignemntProperties).not.toHaveLength(0);

        await wrapper.setData({
            sortBy: assignemntProperties[0],
        });

        const listCriteriaSortings = wrapper.vm.listCriteria.sortings;
        expect(listCriteriaSortings).toHaveLength(1);
        const sorting = listCriteriaSortings[0];
        expect(sorting.type).toBe('count');
        expect(sorting.field).toMatch(/\.id$/);
    });

    it('should notify on inline edit save error', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.vm.onInlineEditSave(Promise.reject());

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-settings-rule.detail.messageSaveError',
        });
    });

    it('should notify on inline edit save success', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        wrapper.vm.createNotificationSuccess = jest.fn();

        const rule = {
            name: 'foo',
        };
        await wrapper.vm.onInlineEditSave(Promise.resolve(), rule);

        expect(wrapper.vm.createNotificationSuccess).toHaveBeenCalledWith({
            message: 'sw-settings-rule.detail.messageSaveSuccess',
        });
    });

    it('should set loading state to false on getList error', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        wrapper.vm.ruleRepository.search = jest.fn();
        wrapper.vm.ruleRepository.search.mockRejectedValueOnce(false);

        await wrapper.vm.getList();
        await flushPromises();

        expect(wrapper.vm.ruleRepository.search).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('should set languageId on language switch change', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.vm.onChangeLanguage('foo');
        expect(Shopware.State.get('context').api.languageId).toBe('foo');
    });
});
