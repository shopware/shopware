import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-cms/component/sw-cms-missing-element-modal';
import 'src/app/component/base/sw-modal';
import 'src/app/component/base/sw-button';

const { Component } = Shopware;

function createWrapper() {
    return shallowMount(Component.build('sw-cms-missing-element-modal'), {
        propsData: {
            missingElements: []
        },
        mocks: {
            $t: key => key,
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
            'sw-modal': Component.build('sw-modal'),
            'sw-button': Component.build('sw-button'),
            'sw-icon': true
        }
    });
}

describe('module/sw-cms/component/sw-cms-missing-element-modal', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should emit an event when clicking on cancel button', async () => {
        await wrapper.find('.sw-cms-missing-element-modal__button-cancel').trigger('click');

        const pageChangeEvents = wrapper.emitted()['modal-close'];

        expect(pageChangeEvents.length).toBe(1);
    });

    it('should emit an event when clicking on save button', async () => {
        await wrapper.find('.sw-cms-missing-element-modal__button-save').trigger('click');

        const pageChangeEvents = wrapper.emitted()['modal-save'];

        expect(pageChangeEvents.length).toBe(1);
    });

    it('should expose no missing element', async () => {
        const title = await wrapper.find('.sw-cms-missing-element-modal__title');

        expect(title.text()).toEqual(
            'sw-cms.components.cmsMissingElementModal.title{"element":""}'
        );
    });

    it('should expose one missing element', async () => {
        await wrapper.setProps({
            missingElements: ['buyBox']
        });

        const title = await wrapper.find('.sw-cms-missing-element-modal__title');

        expect(title.text()).toEqual(
            'sw-cms.components.cmsMissingElementModal.title{"element":"sw-cms.elements.buyBox.label"}'
        );
    });

    it('should expose two missing elements', async () => {
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
        await wrapper.setProps({
            missingElements: ['buyBox', 'productDescriptionReviews', 'crossSelling']
        });

        const title = await wrapper.find('.sw-cms-missing-element-modal__title');

        expect(title.text()).toEqual(
            // eslint-disable-next-line max-len
            'sw-cms.components.cmsMissingElementModal.title{"element":"sw-cms.elements.buyBox.label, sw-cms.elements.productDescriptionReviews.label, sw-cms.elements.crossSelling.label"}'
        );
    });
});
