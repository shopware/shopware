import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings-rule/page/sw-settings-rule-detail';

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

function createWrapper(privileges = [], isNewRule = false) {
    return shallowMount(Shopware.Component.build('sw-settings-rule-detail'), {
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
            'router-view': true
        },
        propsData: {
            ruleId: isNewRule ? null : 'uuid1'
        },
        provide: {
            ruleConditionDataProviderService: {
                getModuleTypes: () => []
            },
            repositoryFactory: {
                create: () => {
                    return {
                        create: () => {
                            return createRuleMock(true);
                        },
                        get: () => Promise.resolve(createRuleMock(false))
                    };
                }
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            }

        },
        mocks: {
            $route: {
                meta: {
                },
                params: {
                    id: ''
                }
            }
        }
    });
}

describe('src/module/sw-settings-rule/page/sw-settings-rule-detail', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should have disabled fields', async () => {
        const wrapper = createWrapper();

        const buttonSave = wrapper.find('.sw-settings-rule-detail__save-action');

        expect(buttonSave.attributes().disabled).toBe('true');
    });

    it('should have enabled fields', async () => {
        const wrapper = createWrapper([
            'rule.editor'
        ]);

        const buttonSave = wrapper.find('.sw-settings-rule-detail__save-action');

        expect(buttonSave.attributes().disabled).toBeUndefined();
    });

    it('should render tabs in existing rule', async () => {
        const wrapper = createWrapper([
            'rule.editor'
        ]);

        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-settings-rule-detail__tabs').exists()).toBeTruthy();
    });

    it('should not render tabs in new rule', async () => {
        const wrapper = createWrapper([
            'rule.editor'
        ], true);

        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-settings-rule-detail__tabs').exists()).toBeFalsy();
    });
});
