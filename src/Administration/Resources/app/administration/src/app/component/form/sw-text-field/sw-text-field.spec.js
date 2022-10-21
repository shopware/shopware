import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/sw-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-contextual-field';

const { Component } = Shopware;

function createWrapper(options = {}) {
    const localVue = createLocalVue();

    return shallowMount(Component.build('sw-text-field'), {
        localVue,
        stubs: {
            'sw-field': Component.build('sw-field'),
            'sw-base-field': Component.build('sw-base-field'),
            'sw-contextual-field': Component.build('sw-contextual-field'),
            'sw-block-field': Component.build('sw-block-field'),
            'sw-field-error': true
        },
        provide: {
            validationService: {}
        },
        ...options
    });
}

describe('src/app/component/form/sw-text-field', () => {
    const localVue = createLocalVue();

    Component.register('sw-text-field-mock', {
        template:
            '<div>' +
                '<sw-text-field v-model="mockVar" class="no-suffix"></sw-text-field>' +
                '<sw-text-field v-model="mockVar" class="with-suffix" idSuffix="iShallBeSuffix"></sw-text-field>' +
            '</div>',

        data() {
            return {
                mockVar: 'content'
            };
        }
    });

    const usageWrapper = shallowMount(Component.build('sw-text-field-mock'), {
        localVue,
        stubs: {
            'sw-text-field': Component.build('sw-text-field'),
            'sw-base-field': Component.build('sw-base-field'),
            'sw-contextual-field': Component.build('sw-contextual-field'),
            'sw-block-field': Component.build('sw-block-field'),
            'sw-field-error': true
        },
        provide: {
            validationService: {}
        }
    });

    it('should be a Vue.js component', () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should render without idSuffix corretly', () => {
        const noSuffix = usageWrapper.find('.no-suffix');

        expect(noSuffix.exists()).toBeTruthy();
        expect(noSuffix.find('#sw-field--mockVar').exists()).toBeTruthy();
    });

    it('should render with idSuffix corretly and generated a correct HTML-ID', () => {
        const withSuffix = usageWrapper.find('.with-suffix');

        expect(withSuffix.exists()).toBeTruthy();
        expect(withSuffix.find('#sw-field--mockVar-iShallBeSuffix').exists()).toBeTruthy();
    });

    it('should render with custom html attributes like minlength and maxlength', () => {
        const wrapper = createWrapper({
            attrs: {
                maxlength: '12',
                minlength: '4'
            }
        });

        expect(wrapper.find('input[type="text"]').attributes().maxlength).toBe('12');
        expect(wrapper.find('input[type="text"]').attributes().minlength).toBe('4');
    });

    it('should show the label from the property', () => {
        const wrapper = createWrapper({
            propsData: {
                label: 'Label from prop'
            }
        });

        expect(wrapper.find('label').text()).toEqual('Label from prop');
    });

    it('should show the value from the label slot', () => {
        const wrapper = createWrapper({
            propsData: {
                label: 'Label from prop'
            },
            scopedSlots: {
                label: '<template>Label from slot</template>'
            }
        });

        expect(wrapper.find('label').text()).toEqual('Label from slot');
    });
});
