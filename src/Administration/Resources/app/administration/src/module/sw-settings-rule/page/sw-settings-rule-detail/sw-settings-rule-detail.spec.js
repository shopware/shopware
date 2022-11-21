import { shallowMount } from '@vue/test-utils';
import { kebabCase } from 'lodash';
import swSettingsRuleDetail from 'src/module/sw-settings-rule/page/sw-settings-rule-detail';

const { EntityCollection } = Shopware.Data;

Shopware.Component.register('sw-settings-rule-detail', swSettingsRuleDetail);

function createRuleMock(isNew) {
    return {
        name: 'Test rule',
        isNew: () => isNew,
        conditions: {
            entity: 'rule',
            source: 'foo/rule'
        }
    };
}

function getCollection(repository) {
    return new EntityCollection(
        `/${kebabCase(repository)}`,
        repository,
        null,
        { isShopwareContext: true },
        [],
        0,
        null
    );
}

async function createWrapper(privileges = [], isNewRule = false, computed = {}) {
    return shallowMount(await Shopware.Component.build('sw-settings-rule-detail'), {
        stubs: {
            'sw-page': {
                template: `
    <div>
        <slot name="smart-bar-actions"></slot>
        <slot name="content"></slot>
    </div>`
            },
            'sw-button': true,
            'sw-button-process': true,
            'sw-card': true,
            'sw-card-view': true,
            'sw-container': true,
            'sw-field': true,
            'sw-multi-select': true,
            'sw-condition-tree': true,
            'sw-tabs': true,
            'sw-tabs-item': true,
            'router-view': true,
            'sw-skeleton': true,
            'sw-context-menu-item': true,
            'sw-context-button': true,
            'sw-button-group': true,
            'sw-icon': true,
            'sw-discard-changes-modal': {
                template: `
    <div>
        Iam here
    </div>`
            }
        },
        propsData: {
            ruleId: isNewRule ? null : 'uuid1'
        },
        provide: {
            ruleConditionDataProviderService: {
                getModuleTypes: () => [],
                addScriptConditions: () => {}
            },
            ruleConditionsConfigApiService: {
                load: () => Promise.resolve()
            },
            repositoryFactory: {
                create: (repository) => {
                    return {
                        create: () => {
                            return createRuleMock(true);
                        },
                        get: () => Promise.resolve(createRuleMock(false)),
                        search: () => Promise.resolve(getCollection(repository)),
                        hasChanges: (rule, hasChanges) => { return hasChanges ?? false; },
                    };
                }
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            },
            feature: {
                isActive: () => true
            },
        },
        mocks: {
            $route: {
                meta: {
                },
                params: {
                    id: ''
                }
            }
        },
        computed,
    });
}

describe('src/module/sw-settings-rule/page/sw-settings-rule-detail', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should have disabled fields', async () => {
        const wrapper = await createWrapper();

        await flushPromises();

        const buttonSave = wrapper.find('.sw-settings-rule-detail__save-action');

        expect(buttonSave.attributes().disabled).toBe('true');
    });

    it('should have enabled fields', async () => {
        const wrapper = await createWrapper([
            'rule.editor'
        ]);

        await flushPromises();

        const buttonSave = wrapper.find('.sw-settings-rule-detail__save-action');

        expect(buttonSave.attributes().disabled).toBeUndefined();
    });

    it('should render tabs in existing rule', async () => {
        const wrapper = await createWrapper([
            'rule.editor'
        ]);

        await flushPromises();

        expect(wrapper.find('.sw-settings-rule-detail__tabs').exists()).toBeTruthy();
    });

    it('should not render tabs in new rule', async () => {
        const wrapper = await createWrapper([
            'rule.editor'
        ], true);

        await flushPromises();

        expect(wrapper.find('.sw-settings-rule-detail__tabs').exists()).toBeFalsy();
    });

    it('should set user changes when condition tree changed', async () => {
        const wrapper = await createWrapper([
            'rule.editor'
        ], false);

        await flushPromises();

        expect(wrapper.vm.conditionsTreeContainsUserChanges).toBeFalsy();
        wrapper.vm.setTreeFinishedLoading();
        wrapper.vm.conditionsChanged({ conditions: [], deletedIds: [] });
        await wrapper.vm.$nextTick();
        expect(wrapper.vm.conditionsTreeContainsUserChanges).toBeTruthy();
    });

    it('should open changes modal when leaving the route', async () => {
        const wrapper = await createWrapper([
            'rule.editor'
        ], false);

        await flushPromises();

        await wrapper.setData({
            conditionsTreeContainsUserChanges: true
        });

        const next = jest.fn();
        wrapper.vm.unsavedDataLeaveHandler(
            { name: 'sw.settings.rule.detail.assignments' }, { name: 'sw.settings.rule.detail.base' }, next
        );


        expect(next).toHaveBeenCalled();
    });

    it('should reset condition tree state when navigating back to the base tab', async () => {
        const wrapper = await createWrapper([
            'rule.editor'
        ], false);

        await flushPromises();

        await wrapper.setData({
            conditionsTreeContainsUserChanges: true
        });

        const next = jest.fn();
        wrapper.vm.unsavedDataLeaveHandler(
            { name: 'sw.settings.rule.detail.base' }, {}, next
        );

        expect(wrapper.vm.conditionsTreeContainsUserChanges).toBeFalsy();
        expect(wrapper.vm.conditionTreeFinishedLoading).toBeFalsy();
        expect(next).toHaveBeenCalled();
    });

    it('should not open changes modal when there are no changes', async () => {
        const wrapper = await createWrapper([
            'rule.editor'
        ], false);

        await flushPromises();

        await wrapper.setData({
            conditionsTreeContainsUserChanges: false
        });

        const next = jest.fn();
        wrapper.vm.unsavedDataLeaveHandler(
            {}, {}, next
        );

        expect(wrapper.vm.isDisplayingSaveChangesWarning).toBeFalsy();
        expect(next).toHaveBeenCalled();
    });

    it('should return tab has no error for assignment tab', async () => {
        const wrapper = await createWrapper();

        await flushPromises();

        expect(wrapper.vm.tabHasError({
            route: {
                name: 'sw.settings.rule.detail.assignments',
            },
        })).toBe(false);
    });

    it('should return tab has error for assignment tab', async () => {
        const wrapper = await createWrapper(
            [],
            false,
            {
                ruleNameError() {
                    return {
                        detail: 'error'
                    };
                }
            }
        );

        await flushPromises();

        expect(wrapper.vm.tabHasError({
            route: {
                name: 'sw.settings.rule.detail.base',
            },
        })).toBe(true);
    });
});
