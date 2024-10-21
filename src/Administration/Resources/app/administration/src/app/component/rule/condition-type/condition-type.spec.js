/**
 * @package services-settings
 */
/* global adminPath */
import { mount } from '@vue/test-utils';
import 'src/app/component/rule/sw-condition-base';
import 'src/app/component/rule/sw-condition-base-line-item';
import ConditionDataProviderService from 'src/app/service/rule-condition.service';
import fs from 'fs';
// eslint-disable-next-line
import path from 'path';
import ruleConditionsConfig from './_mocks/ruleConditionsConfig.json';

const conditionTypesRootPath = 'src/app/component/rule/condition-type/';
const conditionTypes = fs.readdirSync(path.join(adminPath, conditionTypesRootPath)).filter((conditionType) => {
    return conditionType.match(/^(?!_mocks)[a-z-]*(?<!\.spec)$/);
});

function importAllConditionTypes() {
    return Promise.all(
        conditionTypes.map((conditionType) => {
            return import(path.join(adminPath, conditionTypesRootPath, conditionType));
        }),
    );
}

async function createWrapperForComponent(componentName, props = {}) {
    return mount(await Shopware.Component.build(componentName), {
        props: {
            condition: {},
            ...props,
        },
        global: {
            stubs: {
                'sw-field-error': {
                    template: '<div class="sw-field-error"></div>',
                },
                'sw-context-menu-item': {
                    template: '<div class="sw-context-menu-item"></div>',
                },
                'sw-context-button': {
                    template: '<div class="sw-context-button"></div>',
                },
                'sw-number-field': {
                    template: '<div class="sw-number-field"></div>',
                },
                'sw-condition-type-select': {
                    template: '<div class="sw-condition-type-select"></div>',
                },
                'sw-condition-operator-select': {
                    template: '<div class="sw-condition-operator-select"></div>',
                },
                'sw-condition-is-net-select': {
                    template: '<div class="sw-condition-is-net-select"></div>',
                },
                'sw-entity-multi-select': {
                    template: '<div class="sw-entity-multi-select"></div>',
                },
                'sw-entity-single-select': {
                    template: '<div class="sw-entity-single-select"></div>',
                },
                'sw-text-field': {
                    template: '<div class="sw-text-field"></div>',
                },
                'sw-tagged-field': {
                    template: '<div class="sw-tagged-field"></div>',
                },
                'sw-single-select': {
                    template: '<div class="sw-single-select"></div>',
                },
                'sw-entity-tag-select': {
                    template: '<div class="sw-entity-tag-select"></div>',
                },
                'sw-datepicker': {
                    template: '<div class="sw-datepicker"></div>',
                },
                'sw-button': {
                    template: '<div class="sw-button"></div>',
                },
                'sw-icon': {
                    template: '<div class="sw-icon"></div>',
                },
                'sw-textarea-field': {
                    template: '<div class="sw-textarea-field"></div>',
                },
                'sw-form-field-renderer': true,
                'sw-condition-unit-menu': true,
                'sw-condition-modal': true,
                'sw-product-variant-info': true,
                'sw-select-result': true,
                'sw-highlight-text': true,
                'sw-help-text': true,
            },
            provide: {
                conditionDataProviderService: new ConditionDataProviderService(),
                ruleConditionsConfigApiService: {
                    load: () => Promise.resolve(),
                },
                availableTypes: [],
                availableGroups: [],
                restrictedConditions: [],
                childAssociationField: {},
                repositoryFactory: {
                    create: () => ({}),
                },
                insertNodeIntoTree: () => ({}),
                removeNodeFromTree: () => ({}),
                createCondition: () => ({}),
                conditionScopes: [],
                unwrapAllLineItemsCondition: () => ({}),
            },
        },
    });
}

function eachField(fieldTypes, callbackFunction) {
    fieldTypes.forEach((fieldType) =>
        fieldType.forEach((field) => {
            callbackFunction(field);
        }),
    );
}

function getAllFields(wrapper) {
    return [
        wrapper.findAll('.sw-context-menu-item'),
        wrapper.findAll('.sw-context-button'),
        wrapper.findAll('.sw-number-field'),
        wrapper.findAll('.sw-condition-type-select'),
        wrapper.findAll('.sw-condition-operator-select'),
        wrapper.findAll('.sw-entity-multi-select'),
        wrapper.findAll('.sw-entity-single-select'),
        wrapper.findAll('.sw-text-field'),
        wrapper.findAll('.sw-tagged-field'),
        wrapper.findAll('.sw-single-select'),
        wrapper.findAll('.sw-entity-tag-select'),
        wrapper.findAll('.sw-datepicker'),
        wrapper.findAll('.sw-button'),
        wrapper.findAll('.sw-textarea-field'),
    ];
}

describe('src/app/component/rule/condition-type/*.js', () => {
    beforeAll(async () => {
        await importAllConditionTypes();
    });

    beforeEach(() => {
        Shopware.State.commit('ruleConditionsConfig/setConfig', ruleConditionsConfig);
    });

    it.each(conditionTypes)('The component "%s" should have all fields enabled', async (conditionType) => {
        const wrapper = await createWrapperForComponent(conditionType);
        await flushPromises();

        eachField(getAllFields(wrapper), (field) => {
            // Handle edge case
            if (conditionType === 'sw-condition-not-found' && field.classes().includes('sw-textarea-field')) {
                return;
            }

            expect(field.attributes().disabled).toBeUndefined();
        });
    });

    it.each(conditionTypes)('The component "%s" should have all fields disabled', async (conditionType) => {
        const wrapper = await createWrapperForComponent(conditionType, {
            disabled: true,
        });
        await flushPromises();

        eachField(getAllFields(wrapper), (field) => {
            expect(field.attributes().disabled).toBeDefined();
        });
    });
});
