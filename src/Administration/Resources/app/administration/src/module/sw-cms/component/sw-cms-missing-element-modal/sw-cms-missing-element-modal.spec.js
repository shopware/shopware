/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-cms-missing-element-modal', {
            sync: true,
        }),
        {
            props: {
                missingElements: [],
            },
            global: {
                mocks: {
                    $tc: (key, number, value) => {
                        if (!value) {
                            return key;
                        }
                        return key + JSON.stringify(value);
                    },
                },
                provide: {
                    shortcutService: {
                        startEventListener: () => {},
                        stopEventListener: () => {},
                    },
                },
                stubs: {
                    'sw-modal': await wrapTestComponent('sw-modal'),
                    'sw-button': true,
                    'sw-icon': true,
                    'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field'),
                    'sw-checkbox-field-deprecated': await wrapTestComponent('sw-checkbox-field-deprecated', { sync: true }),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-field-error': await wrapTestComponent('sw-field-error'),
                    'sw-loader': true,
                    'sw-inheritance-switch': true,
                    'sw-ai-copilot-badge': true,
                    'sw-help-text': true,
                },
            },
        },
    );
}

describe('module/sw-cms/component/sw-cms-missing-element-modal', () => {
    it('should emit an event when clicking on cancel button', async () => {
        const wrapper = await createWrapper();

        wrapper.findComponent('.sw-cms-missing-element-modal__button-cancel').vm.$emit('click');

        const pageChangeEvents = wrapper.emitted('modal-close');

        expect(pageChangeEvents).toHaveLength(1);
    });

    it('should emit an event when clicking on save button', async () => {
        const wrapper = await createWrapper();

        wrapper.findComponent('.sw-cms-missing-element-modal__button-save').vm.$emit('click');

        const pageChangeEvents = wrapper.emitted('modal-save');

        expect(pageChangeEvents).toHaveLength(1);
    });

    it('should emit an event when check on dont remind checkbox', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.sw-cms-missing-element-modal__dont-remind').find('input').trigger('change');

        const pageChangeEvents = wrapper.emitted()['modal-dont-remind-change'];

        expect(pageChangeEvents).toHaveLength(1);
    });

    it('should expose no missing element', async () => {
        const wrapper = await createWrapper();

        const title = await wrapper.find('.sw-cms-missing-element-modal__title');

        expect(title.text()).toBe('sw-cms.components.cmsMissingElementModal.title{"element":""}');
    });

    it('should expose one missing element', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            missingElements: ['buyBox'],
        });

        const title = await wrapper.find('.sw-cms-missing-element-modal__title');

        expect(title.text()).toBe(
            'sw-cms.components.cmsMissingElementModal.title{"element":"sw-cms.elements.buyBox.label"}',
        );
    });

    it('should expose two missing elements', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            missingElements: [
                'buyBox',
                'productDescriptionReviews',
            ],
        });

        const title = await wrapper.find('.sw-cms-missing-element-modal__title');

        expect(title.text()).toBe(
            // eslint-disable-next-line max-len
            'sw-cms.components.cmsMissingElementModal.title{"element":"sw-cms.elements.buyBox.label, sw-cms.elements.productDescriptionReviews.label"}',
        );
    });

    it('should expose three missing elements', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            missingElements: [
                'buyBox',
                'productDescriptionReviews',
                'crossSelling',
            ],
        });

        const title = wrapper.find('.sw-cms-missing-element-modal__title');

        expect(title.text()).toBe(
            // eslint-disable-next-line max-len
            'sw-cms.components.cmsMissingElementModal.title{"element":"sw-cms.elements.buyBox.label, sw-cms.elements.productDescriptionReviews.label, sw-cms.elements.crossSelling.label"}',
        );
    });
});
