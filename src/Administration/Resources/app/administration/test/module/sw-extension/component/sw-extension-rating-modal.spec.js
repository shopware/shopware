import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-extension/component/sw-ratings/sw-extension-rating-modal';
import 'src/module/sw-extension/component/sw-ratings/sw-extension-review-creation';
import 'src/module/sw-extension/component/sw-ratings/sw-extension-review-creation-inputs';
import 'src/app/component/form/sw-gtc-checkbox';

describe('src/module/sw-extension/component/sw-ratings/sw-extension-rating-modal', () => {
    /** @type Wrapper */
    let wrapper;

    function createWrapper() {
        return shallowMount(Shopware.Component.build('sw-extension-rating-modal'), {
            propsData: {
                extension: {}
            },
            provide: {
                extensionStoreActionService: {}
            },
            mocks: {
                $tc: key => key
            },
            stubs: {
                'sw-modal': {
                    template: `<div class="sw-modal">
    <slot name="default"></slot>
    <slot name="footer"></slot>
</div>`
                },
                'sw-extension-review-creation': Shopware.Component.build('sw-extension-review-creation'),
                'sw-extension-review-creation-inputs': Shopware.Component.build('sw-extension-review-creation-inputs'),
                'sw-gtc-checkbox': Shopware.Component.build('sw-gtc-checkbox'),
                'sw-button': true,
                'sw-field': true,
                'sw-textarea-field': true,
                'sw-extension-select-rating': true,
                'sw-text-field': true,
                'sw-checkbox-field': true,
                'sw-button-process': true
            }
        });
    }

    afterEach(() => {
        if (wrapper) wrapper.destroy();
    });

    it('should be a Vue.js component', () => {
        wrapper = createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });
});
