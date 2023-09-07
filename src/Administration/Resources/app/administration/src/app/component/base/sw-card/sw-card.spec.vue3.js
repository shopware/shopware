/**
 * @package admin
 */

import { mount } from '@vue/test-utils_v3';

async function createWrapper(additionalOptions = {}) {
    return mount(await wrapTestComponent('sw-card'), {
        attachTo: document.body,
        global: {
            stubs: {
                'sw-context-button': await wrapTestComponent('sw-context-button'),
                'sw-loader': true,
                'sw-icon': true,
                'sw-popover': await wrapTestComponent('sw-popover'),
                'sw-context-menu': await wrapTestComponent('sw-context-menu'),
                'sw-ignore-class': true,
                'sw-extension-component-section': true,
            },
        },
        props: {
            positionIdentifier: 'test',
        },
        ...additionalOptions,
    });
}

describe('src/app/component/base/sw-card', () => {
    it('should display title', async () => {
        const options = {
            propsData: {
                positionIdentifier: 'test',
                title: 'test title',
            },
        };
        const wrapper = await createWrapper(options);

        expect(wrapper.find('.sw-card__title').exists()).toBeTruthy();
    });

    it('should display subtitle', async () => {
        const options = {
            propsData: {
                positionIdentifier: 'test',
                subtitle: 'test subtitle',
            },
        };
        const wrapper = await createWrapper(options);

        expect(wrapper.find('.sw-card__subtitle').exists()).toBeTruthy();
    });

    it('should display loader', async () => {
        const options = {
            propsData: {
                positionIdentifier: 'test',
                isLoading: true,
            },
        };
        const wrapper = await createWrapper(options);

        expect(wrapper.find('sw-loader-stub').exists()).toBeTruthy();
    });

    it('should leave an implemented sw-card unaffected if `context-action` has not been slotted', async () => {
        const options = {
            slots: {
                default: 'hello',
            },
        };
        const emptyCard = await createWrapper(options);

        expect(emptyCard.find('.sw-context-button').exists()).toBe(false);
    });

    it('should correctly use the `context-action` slot using unscoped slots', async () => {
        const options = {
            slots: {
                'context-actions': '<div class="unscoped-slot">Unscoped</div>',
            },
        };
        const wrapper = await createWrapper(options);
        await flushPromises();
        const buttonUnscopedCard = wrapper.find('.sw-context-button__button');

        expect(wrapper.find('.sw-context-button__button').exists()).toBeTruthy();
        expect(wrapper.find('.unscoped-slot').exists()).toBe(false);

        await buttonUnscopedCard.trigger('click');
        await flushPromises();
        expect(document.body.querySelector('.unscoped-slot')).toBeInstanceOf(Element);
    });
});
