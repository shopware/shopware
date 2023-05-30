import { shallowMount } from '@vue/test-utils';
import swExtensionRatingModal from 'src/module/sw-extension/component/sw-ratings/sw-extension-rating-modal';
import swExtensionReviewCreation from 'src/module/sw-extension/component/sw-ratings/sw-extension-review-creation';
import swExtensionReviewCreationInputs from 'src/module/sw-extension/component/sw-ratings/sw-extension-review-creation-inputs';
import 'src/app/component/form/sw-gtc-checkbox';

Shopware.Component.register('sw-extension-review-creation', swExtensionReviewCreation);
Shopware.Component.extend('sw-extension-rating-modal', 'sw-extension-review-creation', swExtensionRatingModal);
Shopware.Component.register('sw-extension-review-creation-inputs', swExtensionReviewCreationInputs);

/**
 * @package merchant-services
 */
describe('src/module/sw-extension/component/sw-ratings/sw-extension-rating-modal', () => {
    /** @type Wrapper */
    let wrapper;

    async function createWrapper() {
        return shallowMount(await Shopware.Component.build('sw-extension-rating-modal'), {
            propsData: {
                extension: {},
            },
            provide: {
                extensionStoreActionService: {},
            },
            stubs: {
                'sw-modal': {
                    template: `<div class="sw-modal">
    <slot name="default"></slot>
    <slot name="footer"></slot>
</div>`,
                },
                'sw-extension-review-creation': await Shopware.Component.build('sw-extension-review-creation'),
                'sw-extension-review-creation-inputs': await Shopware.Component.build('sw-extension-review-creation-inputs'),
                'sw-gtc-checkbox': await Shopware.Component.build('sw-gtc-checkbox'),
                'sw-button': true,
                'sw-field': true,
                'sw-textarea-field': true,
                'sw-extension-select-rating': true,
                'sw-text-field': true,
                'sw-checkbox-field': true,
                'sw-button-process': true,
            },
        });
    }

    afterEach(() => {
        if (wrapper) wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });
});
