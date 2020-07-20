import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/app/component/form/sw-custom-field-set-renderer';
import 'src/app/component/utils/sw-inherit-wrapper';
import 'src/app/component/form/sw-form-field-renderer';
import 'src/app/component/form/sw-field';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-field-error';
import 'src/app/component/base/sw-inheritance-switch';
import 'src/app/component/base/sw-icon';

function createWrapper() {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-custom-field-set-renderer'), {
        localVue,
        propsData: {
            sets: [{
                id: 'example',
                name: 'example',
                customFields: [{
                    name: 'customFieldName',
                    type: 'text',
                    config: {
                        label: 'configFieldLabel'
                    }
                }]
            }],
            entity: {
                customFields: {
                    customFieldName: null
                }
            },
            parentEntity: {
                id: 'parentId',
                translated: {
                    customFields: {
                        customFieldName: 'inherit me'
                    }
                }
            }
        },
        stubs: {
            'sw-tabs': '<div><slot name="content"></slot></div>',
            'sw-inherit-wrapper': Shopware.Component.build('sw-inherit-wrapper'),
            'sw-inheritance-switch': Shopware.Component.build('sw-inheritance-switch'),
            'sw-form-field-renderer': Shopware.Component.build('sw-form-field-renderer'),
            'sw-field': Shopware.Component.build('sw-field'),
            'sw-text-field': Shopware.Component.build('sw-text-field'),
            'sw-contextual-field': Shopware.Component.build('sw-contextual-field'),
            'sw-block-field': Shopware.Component.build('sw-block-field'),
            'sw-base-field': Shopware.Component.build('sw-base-field'),
            'sw-field-error': Shopware.Component.build('sw-field-error'),
            'sw-icon': '<div class="sw-icon"></div>'
        },
        provide: {
            next9225: false,
            repositoryFactory: {
                create: () => ({ search: () => Promise.resolve('bar') })
            },
            validationService: {

            }
        },
        mocks: {
            $tc: key => key,
            next9225: true
        }
    });
}

describe('src/app/component/form/sw-custom-field-set-renderer', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.JS component', () => {
        expect(wrapper.isVueInstance()).toBe(true);
    });

    it('should inherit the value from parent entity', () => {
        const customFieldEl = wrapper.find('.sw-inherit-wrapper input[name=customFieldName]');
        expect(customFieldEl.exists()).toBe(true);
        expect(customFieldEl.element.value).toBe('inherit me');
    });
});
