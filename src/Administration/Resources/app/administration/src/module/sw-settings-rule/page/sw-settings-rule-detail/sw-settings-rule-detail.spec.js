import { mount } from '@vue/test-utils_v3';
import { kebabCase } from 'lodash';

const { EntityCollection } = Shopware.Data;

function createRuleMock(isNew) {
    return {
        id: 'uuid1',
        name: 'Test rule',
        isNew: () => isNew,
        getEntityName: () => 'rule',
        conditions: {
            entity: 'rule',
            source: 'foo/rule',
        },
        someRuleRelation: ['some-value'],
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
        null,
    );
}

async function createWrapper(isNewRule = false, provide = {}) {
    return mount(await wrapTestComponent('sw-settings-rule-detail', { sync: true }), {
        props: {
            ruleId: isNewRule ? null : 'uuid1',
        },
        global: {
            renderStubDefaultSlot: true,
            stubs: {
                'sw-page': {
                    template: `
    <div>
        <slot name="smart-bar-actions"></slot>
        <slot name="content"></slot>
    </div>`,
                },
                'sw-button': await wrapTestComponent('sw-button'),
                'sw-button-process': await wrapTestComponent('sw-button-process'),
                'sw-card': true,
                'sw-card-view': true,
                'sw-container': true,
                'sw-field': true,
                'sw-multi-select': true,
                'sw-condition-tree': true,
                'sw-tabs': true,
                'sw-tabs-item': true,
                'router-view': {
                    template: '<div><slot v-bind="{ Component: \'router-test-view\'}"></slot></div>',
                },
                'router-test-view': true,
                'sw-skeleton': true,
                'sw-context-menu-item': true,
                'sw-context-button': true,
                'sw-button-group': true,
                'sw-icon': true,
                'sw-loader': true,
                'sw-discard-changes-modal': {
                    template: `
    <div>
        Iam here
    </div>`,
                },
            },
            provide: {
                ruleConditionDataProviderService: {
                    getModuleTypes: () => [],
                    addScriptConditions: () => {
                    },
                },
                ruleConditionsConfigApiService: {
                    load: () => Promise.resolve(),
                },
                repositoryFactory: {
                    create: (repository) => {
                        return {
                            create: () => {
                                return createRuleMock(true);
                            },
                            get: () => Promise.resolve(createRuleMock(false)),
                            search: () => Promise.resolve(getCollection(repository)),
                            hasChanges: (rule, hasChanges) => {
                                return hasChanges ?? false;
                            },
                            save: () => Promise.resolve(),
                        };
                    },
                },
                ...provide,
            },
            mocks: {
                $route: {
                    meta: {},
                    params: {
                        id: isNewRule ? null : 'uuid1',
                    },
                },
            },
        },
    });
}

describe('src/module/sw-settings-rule/page/sw-settings-rule-detail', () => {
    it('should have disabled fields', async () => {
        global.activeAclRoles = [];

        const wrapper = await createWrapper();
        await flushPromises();

        const buttonSave = wrapper.getComponent('.sw-settings-rule-detail__save-action');

        expect(buttonSave.attributes('disabled')).toBe('');
    });

    it('should have enabled fields', async () => {
        global.activeAclRoles = ['rule.editor'];

        const wrapper = await createWrapper();
        await flushPromises();

        const buttonSave = wrapper.get('.sw-settings-rule-detail__save-action');

        expect(buttonSave.attributes().disabled).toBeUndefined();
    });

    it('should render tabs in existing rule', async () => {
        global.activeAclRoles = ['rule.editor'];

        const wrapper = await createWrapper();

        await flushPromises();

        expect(wrapper.get('.sw-settings-rule-detail__tabs').exists()).toBeTruthy();
    });

    it('should not render tabs in new rule', async () => {
        global.activeAclRoles = ['rule.editor'];

        const wrapper = await createWrapper(true);

        await flushPromises();

        expect(wrapper.find('.sw-settings-rule-detail__tabs').exists()).toBeFalsy();
    });

    it('should set user changes when condition tree changed', async () => {
        global.activeAclRoles = ['rule.editor'];

        const wrapper = await createWrapper(false);

        await flushPromises();

        expect(wrapper.vm.conditionsTreeContainsUserChanges).toBeFalsy();
        wrapper.vm.setTreeFinishedLoading();
        wrapper.vm.conditionsChanged({ conditions: [], deletedIds: [] });
        await wrapper.vm.$nextTick();
        expect(wrapper.vm.conditionsTreeContainsUserChanges).toBeTruthy();
    });

    it('should open changes modal when leaving the route', async () => {
        global.activeAclRoles = ['rule.editor'];

        const wrapper = await createWrapper(false);

        await flushPromises();

        await wrapper.setData({
            conditionsTreeContainsUserChanges: true,
        });

        const next = jest.fn();
        wrapper.vm.unsavedDataLeaveHandler({ name: 'sw.settings.rule.detail.assignments' }, { name: 'sw.settings.rule.detail.base' }, next);


        expect(next).toHaveBeenCalled();
    });

    it('should reset condition tree state when navigating back to the base tab', async () => {
        global.activeAclRoles = ['rule.editor'];

        const wrapper = await createWrapper(false);

        await flushPromises();

        await wrapper.setData({
            conditionsTreeContainsUserChanges: true,
        });

        const next = jest.fn();
        wrapper.vm.unsavedDataLeaveHandler({ name: 'sw.settings.rule.detail.base' }, {}, next);

        expect(wrapper.vm.conditionsTreeContainsUserChanges).toBeFalsy();
        expect(wrapper.vm.conditionTreeFinishedLoading).toBeFalsy();
        expect(next).toHaveBeenCalled();
    });

    it('should not open changes modal when there are no changes', async () => {
        global.activeAclRoles = ['rule.editor',
        ];

        const wrapper = await createWrapper(false);

        await flushPromises();

        await wrapper.setData({
            conditionsTreeContainsUserChanges: false,
        });

        const next = jest.fn();
        wrapper.vm.unsavedDataLeaveHandler({}, {}, next);

        expect(wrapper.vm.isDisplayingSaveChangesWarning).toBeFalsy();
        expect(next).toHaveBeenCalled();
    });

    it('should return tab has no error for assignment tab', async () => {
        global.activeAclRoles = [];

        const wrapper = await createWrapper();

        await flushPromises();

        expect(wrapper.vm.tabHasError({
            route: {
                name: 'sw.settings.rule.detail.assignments',
            },
        })).toBe(false);
    });

    it('should return tab has error for assignment tab', async () => {
        global.activeAclRoles = [];

        Shopware.State.commit('error/addApiError', {
            expression: 'rule.uuid1.name',
            error: {
                message: 'Error detail',
            },
        });

        const wrapper = await createWrapper(false);
        await flushPromises();

        expect(wrapper.vm.tabHasError({
            route: {
                name: 'sw.settings.rule.detail.base',
            },
        })).toBe(true);
    });

    it('should prevent the user from saving the rule when rule awareness is violated', async () => {
        global.activeAclRoles = ['rule.editor'];

        const wrapper = await createWrapper(
            false,
            {
                ruleConditionDataProviderService: {
                    getModuleTypes: () => [],
                    addScriptConditions: () => {},
                    getAwarenessKeysWithEqualsAnyConfig: () => ['someRuleRelation'],
                    getRestrictionsByAssociation: () => ({
                        isRestricted: true,
                    }),
                    getTranslatedConditionViolationList: () => ['someSnippetPath'],
                },
            },
        );

        wrapper.vm.ruleRepository.save = jest.fn(() => Promise.resolve());

        await wrapper.setData({
            conditionTree: [{
                id: 'some-condition',
                children: [{
                    id: 'some-child-condition',
                    children: [{
                        id: 'some-grand-child-condition',
                    }],
                }],
            }],
        });

        await flushPromises();

        const saveButton = wrapper.get('.sw-settings-rule-detail__save-action');
        await saveButton.trigger('click');

        expect(wrapper.vm.ruleRepository.save).toHaveBeenCalledTimes(0);
    });

    it('should save without any awareness config', async () => {
        global.activeAclRoles = ['rule.editor'];

        const wrapper = await createWrapper(
            false,
            {
                ruleConditionDataProviderService: {
                    getModuleTypes: () => [],
                    addScriptConditions: () => {},
                    getAwarenessKeysWithEqualsAnyConfig: () => [],
                },
            },
        );
        wrapper.vm.ruleRepository.save = jest.fn(() => Promise.resolve());

        await wrapper.setData({
            conditionTree: [{
                id: 'some-condition',
                children: [{
                    id: 'some-child-condition',
                    children: [{
                        id: 'some-grand-child-condition',
                    }],
                }],
            }],
        });

        await flushPromises();

        const saveButton = wrapper.get('.sw-settings-rule-detail__save-action');
        await saveButton.trigger('click');

        expect(wrapper.vm.ruleRepository.save).toHaveBeenCalledTimes(1);
    });
});
