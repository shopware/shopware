import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-import-export/component/sw-import-export-edit-profile-modal';
import 'src/app/component/base/sw-tabs';
import 'src/app/component/base/sw-tabs-item';

describe('components/sw-import-export-edit-profile-modal', () => {
    let wrapper;
    let localVue;

    beforeEach(() => {
        localVue = createLocalVue();
        localVue.directive('tooltip', {});

        wrapper = shallowMount(Shopware.Component.build('sw-import-export-edit-profile-modal'), {
            localVue,
            stubs: {
                'sw-modal': true,
                'sw-button': true,
                'sw-tabs': Shopware.Component.build('sw-tabs'),
                'sw-tabs-item': Shopware.Component.build('sw-tabs-item'),
                'sw-container': true,
                'sw-field': {
                    props: ['value'],
                    template: '<div class="sw-field"></div>'
                },
                'sw-text-field': true,
                'sw-single-select': true,
                'sw-import-export-edit-profile-modal-mapping': true
            },
            mocks: {
                $tc: snippetPath => snippetPath,
                $device: { onResize: () => {} }
            },
            provide: {}
        });
    });

    afterEach(() => {
        localVue = null;
        wrapper.destroy();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.isVueInstance()).toBeTruthy();
    });

    it('should not show the variants switch field when product is not selected', () => {
        const variantsSwitch = wrapper.find('.sw-import-export-edit-profile-modal__variants-switch-field');
        expect(variantsSwitch.exists()).toBeFalsy();
    });

    it('should show the variants switch field when product is selected', () => {
        wrapper.setProps({
            profile: {
                sourceEntity: 'product',
                config: {
                    includeVariants: false
                }
            }
        });

        const variantsSwitch = wrapper.find('.sw-import-export-edit-profile-modal__variants-switch-field');
        expect(variantsSwitch.exists()).toBeTruthy();
    });

    it('should not show the variants switch field when product is selected', () => {
        wrapper.setProps({
            profile: {
                sourceEntity: 'media',
                config: {}
            }
        });

        const variantsSwitch = wrapper.find('.sw-import-export-edit-profile-modal__variants-switch-field');
        expect(variantsSwitch.exists()).toBeFalsy();
    });

    it('should show an falsy variants switch field', () => {
        wrapper.setProps({
            profile: {
                sourceEntity: 'product',
                config: {
                    includeVariants: false
                }
            }
        });

        const variantsSwitch = wrapper.find('.sw-import-export-edit-profile-modal__variants-switch-field');

        expect(variantsSwitch.props().value).toBeFalsy();
    });

    it('should show an truthy variants switch field', () => {
        wrapper.setProps({
            profile: {
                sourceEntity: 'product',
                config: {
                    includeVariants: true
                }
            }
        });

        const variantsSwitch = wrapper.find('.sw-import-export-edit-profile-modal__variants-switch-field');

        expect(variantsSwitch.props().value).toBeTruthy();
    });
});
