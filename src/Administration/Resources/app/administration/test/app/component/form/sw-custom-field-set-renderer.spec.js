import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/app/component/form/sw-custom-field-set-renderer';
import 'src/app/component/utils/sw-inherit-wrapper';
import 'src/app/component/form/sw-form-field-renderer';
import 'src/app/component/form/sw-field';
import 'src/app/component/base/sw-tabs';
import 'src/app/component/base/sw-tabs-item';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/sw-switch-field';
import 'src/app/component/form/sw-checkbox-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-field-error';
import 'src/app/component/base/sw-inheritance-switch';
import 'src/app/component/base/sw-icon';

function createEntityCollection(entities = []) {
    return new Shopware.Data.EntityCollection('collection', 'collection', {}, null, entities);
}

function createWrapper(props) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-custom-field-set-renderer'), {
        localVue,
        propsData: props,
        stubs: {
            'sw-tabs': Shopware.Component.build('sw-tabs'),
            'sw-tabs-item': Shopware.Component.build('sw-tabs-item'),
            'sw-inherit-wrapper': Shopware.Component.build('sw-inherit-wrapper'),
            'sw-inheritance-switch': Shopware.Component.build('sw-inheritance-switch'),
            'sw-form-field-renderer': Shopware.Component.build('sw-form-field-renderer'),
            'sw-field': Shopware.Component.build('sw-field'),
            'sw-text-field': Shopware.Component.build('sw-text-field'),
            'sw-contextual-field': Shopware.Component.build('sw-contextual-field'),
            'sw-switch-field': Shopware.Component.build('sw-switch-field'),
            'sw-checkbox-field': Shopware.Component.build('sw-checkbox-field'),
            'sw-entity-multi-select': true,
            'sw-block-field': Shopware.Component.build('sw-block-field'),
            'sw-base-field': Shopware.Component.build('sw-base-field'),
            'sw-field-error': Shopware.Component.build('sw-field-error'),
            'sw-icon': '<div class="sw-icon"></div>'
        },
        provide: {
            repositoryFactory: {
                create: () => ({ search: () => Promise.resolve('bar') })
            },
            validationService: {

            }
        },
        mocks: {
            $tc: key => key,
            $device: {
                onResize: () => {}
            }
        }
    });
}

describe('src/app/component/form/sw-custom-field-set-renderer', () => {
    it('should be a Vue.JS component', () => {
        const wrapper = createWrapper({
            entity: {},
            sets: []
        });
        expect(wrapper.isVueInstance()).toBe(true);
    });

    it('should inherit the value from parent entity', () => {
        const props = {
            sets: createEntityCollection([{
                id: 'example',
                name: 'example',
                config: {},
                customFields: [{
                    name: 'customFieldName',
                    type: 'text',
                    config: {
                        label: 'configFieldLabel'
                    }
                }]
            }]),
            entity: {
                customFields: {
                    customFieldName: null
                },
                customFieldSetSelectionActive: null,
                customFieldSets: createEntityCollection()
            },
            parentEntity: {
                id: 'parentId',
                translated: {
                    customFields: {
                        customFieldName: 'inherit me'
                    }
                },
                customFieldSetSelectionActive: null,
                customFieldSets: []
            }
        };
        const wrapper = createWrapper(props);

        const customFieldEl = wrapper.find('.sw-inherit-wrapper input[name=customFieldName]');
        expect(customFieldEl.exists()).toBe(true);
        expect(customFieldEl.element.value).toBe('inherit me');
    });

    it('should not filter custom field sets when selection not active', () => {
        const props = {
            entity: {
                customFields: {
                    field1: null
                },
                customFieldSetSelectionActive: true,
                customFieldSets: createEntityCollection([{ id: 'set2' }]),
                getEntityName: () => {
                    return 'product';
                }
            },
            sets: createEntityCollection([{
                id: 'set1',
                name: 'set1',
                config: {},
                customFields: [{
                    name: 'field1',
                    type: 'text',
                    config: {
                        label: 'field1Label'
                    }
                }]
            },
            {
                id: 'set2',
                name: 'set2',
                config: {},
                customFields: [{
                    name: 'field2',
                    type: 'text',
                    config: {
                        label: 'field2Label'
                    }
                }]
            }]),
            showCustomFieldSetSelection: false
        };

        const wrapper = createWrapper(props);

        expect(wrapper.vm.filterCustomFields).toBe(false);
        expect(wrapper.vm.visibleCustomFieldSets).toHaveLength(2);
        const tabs = wrapper.findAll('.sw-tabs__content .sw-tabs-item');
        expect(tabs).toHaveLength(2);
    });

    it('should not filter custom field sets when entity has no customFieldSets column', () => {
        const props = {
            entity: {
                customFields: {
                    field1: null
                },
                customFieldSetSelectionActive: null
            },
            sets: createEntityCollection([{
                id: 'set1',
                name: 'set1',
                config: {},
                customFields: [{
                    name: 'field1',
                    type: 'text',
                    config: {
                        label: 'field1Label'
                    }
                }]
            },
            {
                id: 'set2',
                name: 'set2',
                config: {},
                customFields: [{
                    name: 'field2',
                    type: 'text',
                    config: {
                        label: 'field2Label'
                    }
                }]
            }]),
            showCustomFieldSetSelection: true
        };

        const wrapper = createWrapper(props);

        expect(wrapper.vm.filterCustomFields).toBe(false);
        expect(wrapper.vm.visibleCustomFieldSets).toHaveLength(2);
        const tabs = wrapper.findAll('.sw-tabs__content .sw-tabs-item');
        expect(tabs).toHaveLength(2);
    });

    it('should not filter custom field sets when entity has no customFieldSetSelectionActive column', () => {
        const props = {
            entity: {
                customFields: {
                    field1: null
                },
                customFieldSets: createEntityCollection([{ id: 'set2' }])
            },
            sets: createEntityCollection([{
                id: 'set1',
                name: 'set1',
                config: {},
                customFields: [{
                    name: 'field1',
                    type: 'text',
                    config: {
                        label: 'field1Label'
                    }
                }]
            },
            {
                id: 'set2',
                name: 'set2',
                config: {},
                customFields: [{
                    name: 'field2',
                    type: 'text',
                    config: {
                        label: 'field2Label'
                    }
                }]
            }]),
            showCustomFieldSetSelection: true
        };

        const wrapper = createWrapper(props);

        expect(wrapper.vm.filterCustomFields).toBe(false);
        expect(wrapper.vm.visibleCustomFieldSets).toHaveLength(2);
        const tabs = wrapper.findAll('.sw-tabs__content .sw-tabs-item');
        expect(tabs).toHaveLength(2);
    });

    it('should not filter custom field sets when entity has no parent and customFieldSetSelectionActive not set', () => {
        const props = {
            entity: {
                customFields: {
                    field1: null
                },
                customFieldSets: createEntityCollection([{ id: 'set2' }]),
                customFieldSetSelectionActive: null
            },
            sets: createEntityCollection([{
                id: 'set1',
                name: 'set1',
                config: {},
                customFields: [{
                    name: 'field1',
                    type: 'text',
                    config: {
                        label: 'field1Label'
                    }
                }]
            },
            {
                id: 'set2',
                name: 'set2',
                config: {},
                customFields: [{
                    name: 'field2',
                    type: 'text',
                    config: {
                        label: 'field2Label'
                    }
                }]
            }]),
            showCustomFieldSetSelection: true
        };

        const wrapper = createWrapper(props);

        expect(wrapper.vm.filterCustomFields).toBe(false);
        expect(wrapper.vm.visibleCustomFieldSets).toHaveLength(2);
        const tabs = wrapper.findAll('.sw-tabs__content .sw-tabs-item');
        expect(tabs).toHaveLength(2);
    });

    it('should not filter custom field sets when customFieldSetSelectionActive not set and parent has no selection', () => {
        const props = {
            entity: {
                customFields: {
                    field1: null
                },
                customFieldSets: createEntityCollection([{ id: 'set2' }]),
                customFieldSetSelectionActive: null
            },
            sets: createEntityCollection([{
                id: 'set1',
                name: 'set1',
                config: {},
                customFields: [{
                    name: 'field1',
                    type: 'text',
                    config: {
                        label: 'field1Label'
                    }
                }]
            },
            {
                id: 'set2',
                name: 'set2',
                config: {},
                customFields: [{
                    name: 'field2',
                    type: 'text',
                    config: {
                        label: 'field2Label'
                    }
                }]
            }]),
            parentEntity: {
                id: 'parentId'
            },
            showCustomFieldSetSelection: true
        };

        const wrapper = createWrapper(props);

        expect(wrapper.vm.filterCustomFields).toBe(false);
        expect(wrapper.vm.visibleCustomFieldSets).toHaveLength(2);
        const tabs = wrapper.findAll('.sw-tabs__content .sw-tabs-item');
        expect(tabs).toHaveLength(2);
    });

    it('should filter custom field sets when selection active and customFields selected', () => {
        const props = {
            entity: {
                customFields: {
                    field1: null
                },
                customFieldSets: createEntityCollection([{ id: 'set2' }]),
                customFieldSetSelectionActive: true,
                getEntityName: () => {
                    return 'product';
                }
            },
            sets: createEntityCollection([{
                id: 'set1',
                name: 'set1',
                config: {},
                customFields: [{
                    name: 'field1',
                    type: 'text',
                    config: {
                        label: 'field1Label'
                    }
                }]
            },
            {
                id: 'set2',
                name: 'set2',
                config: {},
                customFields: [{
                    name: 'field2',
                    type: 'text',
                    config: {
                        label: 'field2Label'
                    }
                }]
            }]),
            showCustomFieldSetSelection: true
        };

        const wrapper = createWrapper(props);

        expect(wrapper.vm.filterCustomFields).toBe(true);
        expect(wrapper.vm.visibleCustomFieldSets).toHaveLength(1);
        expect(wrapper.vm.visibleCustomFieldSets[0].id).toBe('set2');

        const tabs = wrapper.findAll('.sw-tabs__content .sw-tabs-item');
        expect(tabs).toHaveLength(1);
    });

    it('should filter custom field sets from parent when inherited', () => {
        const props = {
            entity: {
                customFields: {
                    field1: null
                },
                customFieldSets: createEntityCollection(),
                customFieldSetSelectionActive: null,
                getEntityName: () => {
                    return 'product';
                }
            },
            sets: createEntityCollection([{
                id: 'set1',
                name: 'set1',
                config: {},
                customFields: [{
                    name: 'field1',
                    type: 'text',
                    config: {
                        label: 'field1Label'
                    }
                }]
            },
            {
                id: 'set2',
                name: 'set2',
                config: {},
                customFields: [{
                    name: 'field2',
                    type: 'text',
                    config: {
                        label: 'field2Label'
                    }
                }]
            }]),
            parentEntity: {
                id: 'parent',
                customFieldSets: createEntityCollection([{ id: 'set2' }]),
                customFieldSetSelectionActive: true
            },
            showCustomFieldSetSelection: true
        };

        const wrapper = createWrapper(props);

        expect(wrapper.vm.filterCustomFields).toBe(true);
        expect(wrapper.vm.visibleCustomFieldSets).toHaveLength(1);
        expect(wrapper.vm.visibleCustomFieldSets[0].id).toBe('set2');

        const tabs = wrapper.findAll('.sw-tabs__content .sw-tabs-item');
        expect(tabs).toHaveLength(1);
    });
});
