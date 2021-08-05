/* global adminPath */
import { shallowMount } from '@vue/test-utils';
import 'src/app/component/rule/sw-condition-base';
import ConditionDataProviderService from 'src/app/service/rule-condition.service';
import fs from 'fs';
// eslint-disable-next-line
import path from 'path';

const conditionTypesRootPath = 'src/app/component/rule/condition-type/';
const conditionTypes = fs.readdirSync(path.join(adminPath, conditionTypesRootPath));

function importAllConditionTypes() {
    return Promise.all(conditionTypes.map(conditionType => {
        return import(path.join(adminPath, conditionTypesRootPath, conditionType));
    }));
}

function createWrapperForComponent(componentName, props = {}) {
    return shallowMount(Shopware.Component.build(componentName), {
        stubs: {
            'sw-field-error': {
                template: '<div class="sw-field-error"></div>'
            },
            'sw-context-menu-item': {
                template: '<div class="sw-context-menu-item"></div>'
            },
            'sw-context-button': {
                template: '<div class="sw-context-button"></div>'
            },
            'sw-number-field': {
                template: '<div class="sw-number-field"></div>'
            },
            'sw-condition-type-select': {
                template: '<div class="sw-condition-type-select"></div>'
            },
            'sw-condition-operator-select': {
                template: '<div class="sw-condition-operator-select"></div>'
            },
            'sw-condition-is-net-select': {
                template: '<div class="sw-condition-is-net-select"></div>'
            },
            'sw-entity-multi-select': {
                template: '<div class="sw-entity-multi-select"></div>'
            },
            'sw-entity-single-select': {
                template: '<div class="sw-entity-single-select"></div>'
            },
            'sw-text-field': {
                template: '<div class="sw-text-field"></div>'
            },
            'sw-tagged-field': {
                template: '<div class="sw-tagged-field"></div>'
            },
            'sw-single-select': {
                template: '<div class="sw-single-select"></div>'
            },
            'sw-entity-tag-select': {
                template: '<div class="sw-entity-tag-select"></div>'
            },
            'sw-arrow-field': {
                template: '<div class="sw-arrow-field"></div>'
            },
            'sw-datepicker': {
                template: '<div class="sw-datepicker"></div>'
            },
            'sw-button': {
                template: '<div class="sw-button"></div>'
            },
            'sw-icon': {
                template: '<div class="sw-icon"></div>'
            },
            'sw-textarea-field': {
                template: '<div class="sw-textarea-field"></div>'
            }
        },
        provide: {
            conditionDataProviderService: new ConditionDataProviderService(),
            availableTypes: [],
            childAssociationField: {},
            repositoryFactory: {
                create: () => ({})
            }
        },
        propsData: {
            condition: {},
            ...props
        }
    });
}

function eachField(fieldTypes, callbackFunction) {
    fieldTypes.forEach(fieldType => fieldType.wrappers.forEach(field => {
        callbackFunction(field);
    }));
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
        wrapper.findAll('.sw-arrow-field'),
        wrapper.findAll('.sw-datepicker'),
        wrapper.findAll('.sw-button'),
        wrapper.findAll('.sw-textarea-field')
    ];
}

describe('src/app/component/rule/condition-type/*.js', () => {
    beforeAll(() => {
        return importAllConditionTypes();
    });

    it.each(conditionTypes)('The component "%s" should be a mounted successfully', (conditionType) => {
        const wrapper = createWrapperForComponent(conditionType);

        expect(wrapper.vm).toBeTruthy();
    });

    it.each(conditionTypes)('The component "%s" should have all fields enabled', (conditionType) => {
        const wrapper = createWrapperForComponent(conditionType);

        eachField(getAllFields(wrapper), (field) => {
            // Handle edge case
            if (conditionType === 'sw-condition-not-found' && field.classes().includes('sw-textarea-field')) {
                return;
            }

            expect(field.attributes().disabled).toBeUndefined();
        });
    });

    it.each(conditionTypes)('The component "%s" should have all fields disabled', (conditionType) => {
        const wrapper = createWrapperForComponent(conditionType, {
            disabled: true
        });

        eachField(getAllFields(wrapper), (field) => {
            expect(field.attributes().disabled).toBeDefined();
        });
    });
});
