import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-flow/component/modals/sw-flow-create-rule-modal';
import 'src/app/component/base/sw-tabs';
import 'src/app/component/base/sw-tabs-item';
import 'src/app/component/base/sw-button';

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

function createWrapper(privileges = []) {
    return shallowMount(Shopware.Component.build('sw-flow-create-rule-modal'), {
        provide: { repositoryFactory: {
            create: () => {
                return {
                    create: () => {
                        return createRuleMock(true);
                    },
                    get: () => Promise.resolve(createRuleMock(false))
                };
            }
        },
        ruleConditionDataProviderService: {
            getModuleTypes: () => []
        },
        shortcutService: {
            startEventListener: () => {},
            stopEventListener: () => {}
        },

        acl: {
            can: (identifier) => {
                if (!identifier) {
                    return true;
                }

                return privileges.includes(identifier);
            }
        } },

        propsData: {
            sequence: {}
        },

        stubs: {
            'sw-tabs': Shopware.Component.build('sw-tabs'),
            'sw-tabs-item': Shopware.Component.build('sw-tabs-item'),
            'sw-modal': {
                template: `
                    <div class="sw-modal">
                      <slot name="modal-header"></slot>
                      <slot></slot>
                      <slot name="modal-footer"></slot>
                    </div>
                `
            },
            'sw-button': Shopware.Component.build('sw-button'),
            'sw-icon': true,
            'sw-condition-tree': true,
            'sw-container': true,
            'sw-multi-select': true,
            'sw-textarea-field': true,
            'sw-number-field': true,
            'sw-text-field': true
        }
    });
}

describe('module/sw-flow/component/sw-flow-create-rule-modal', () => {
    it('should show the condition on rule tab', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const conditionElement = wrapper.find('.sw-flow-create-rule-modal__rule');
        expect(conditionElement.exists()).toBe(true);
    });

    it('should show these fields on the details tab', async () => {
        const wrapper = createWrapper();
        const fieldClasses = [
            '.sw-flow-create-rule-modal__name',
            '.sw-flow-create-rule-modal__priority',
            '.sw-flow-create-rule-modal__description',
            '.sw-flow-create-rule-modal__type'
        ];

        const detailHeaderTab = wrapper.find('.sw-flow-create-rule-modal__detail-header-tab');
        detailHeaderTab.trigger('click');
        await wrapper.vm.$nextTick();

        fieldClasses.forEach(elementClass => {
            expect(wrapper.find(elementClass).exists()).toBe(true);
        });
    });
});
