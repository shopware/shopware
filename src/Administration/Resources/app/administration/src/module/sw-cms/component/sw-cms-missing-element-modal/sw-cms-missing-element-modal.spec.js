import { shallowMount } from '@vue/test-utils';
import swCmsMissingElementModal from 'src/module/sw-cms/component/sw-cms-missing-element-modal';
import swModal from 'src/app/component/base/sw-modal';

Shopware.Component.register('sw-cms-missing-element-modal', swCmsMissingElementModal);
Shopware.Component.register('sw-modal', swModal);

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-cms-missing-element-modal'), {
        propsData: {
            missingElements: []
        },
        mocks: {
            $tc: (key, number, value) => {
                if (!value) {
                    return key;
                }
                return key + JSON.stringify(value);
            }
        },
        provide: {
            shortcutService: {
                startEventListener: () => {},
                stopEventListener: () => {}
            }
        },
        stubs: {
            'sw-modal': await Shopware.Component.build('sw-modal'),
            'sw-button': true,
            'sw-icon': true
        }
    });
}

describe('module/sw-cms/component/sw-cms-missing-element-modal', () => {
    it('should emit an event when clicking on cancel button', async () => {
        const wrapper = await createWrapper();

        wrapper.find('.sw-cms-missing-element-modal__button-cancel').vm.$emit('click');

        const pageChangeEvents = wrapper.emitted('modal-close');

        expect(pageChangeEvents.length).toBe(1);
    });

    it('should emit an event when clicking on save button', async () => {
        const wrapper = await createWrapper();

        wrapper.find('.sw-cms-missing-element-modal__button-save').vm.$emit('click');

        const pageChangeEvents = wrapper.emitted('modal-save');

        expect(pageChangeEvents.length).toBe(1);
    });

    it('should expose no missing element', async () => {
        const wrapper = await createWrapper();

        const title = await wrapper.find('.sw-cms-missing-element-modal__title');

        expect(title.text()).toEqual(
            'sw-cms.components.cmsMissingElementModal.title{"element":""}'
        );
    });

    it('should expose one missing element', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            missingElements: ['buyBox']
        });

        const title = await wrapper.find('.sw-cms-missing-element-modal__title');

        expect(title.text()).toEqual(
            'sw-cms.components.cmsMissingElementModal.title{"element":"sw-cms.elements.buyBox.label"}'
        );
    });

    it('should expose two missing elements', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            missingElements: ['buyBox', 'productDescriptionReviews']
        });

        const title = await wrapper.find('.sw-cms-missing-element-modal__title');

        expect(title.text()).toEqual(
            // eslint-disable-next-line max-len
            'sw-cms.components.cmsMissingElementModal.title{"element":"sw-cms.elements.buyBox.label, sw-cms.elements.productDescriptionReviews.label"}'
        );
    });

    it('should expose three missing elements', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            missingElements: ['buyBox', 'productDescriptionReviews', 'crossSelling']
        });

        const title = wrapper.find('.sw-cms-missing-element-modal__title');

        expect(title.text()).toEqual(
            // eslint-disable-next-line max-len
            'sw-cms.components.cmsMissingElementModal.title{"element":"sw-cms.elements.buyBox.label, sw-cms.elements.productDescriptionReviews.label, sw-cms.elements.crossSelling.label"}'
        );
    });
});
