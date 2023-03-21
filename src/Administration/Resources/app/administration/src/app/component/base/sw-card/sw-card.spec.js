/**
 * @package admin
 */

import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/app/component/base/sw-card';
import 'src/app/component/context-menu/sw-context-button';

async function createWrapper(additionalOptions = {}) {
    const localVue = createLocalVue();

    return shallowMount(await Shopware.Component.build('sw-card'), {
        localVue,
        stubs: {
            'sw-context-button': await Shopware.Component.build('sw-context-button'),
            'sw-loader': true,
            'sw-icon': true,
            'sw-popover': true,
            'sw-context-menu': true,
            'sw-ignore-class': true,
            'sw-extension-component-section': true,
        },
        propsData: {
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
        const buttonUnscopedCard = wrapper.find('.sw-context-button__button');

        expect(wrapper.find('.sw-context-button__button').exists()).toBeTruthy();
        expect(wrapper.find('.unscoped-slot').exists()).toBe(false);

        await buttonUnscopedCard.trigger('click');
        expect(wrapper.find('.unscoped-slot').exists()).toBeTruthy();
    });

    it('should correctly use the `context-action` slot using scoped slots', async () => {
        const options = {
            scopedSlots: {
                'context-actions': '<div class="scoped-slot">Scoped</div>',
            },
        };
        const wrapper = await createWrapper(options);
        const buttonScopedCard = wrapper.find('.sw-context-button__button');

        expect(wrapper.find('.sw-context-button__button').exists()).toBeTruthy();
        expect(wrapper.find('.scoped-slot').exists()).toBe(false);

        await buttonScopedCard.trigger('click');
        expect(wrapper.find('.scoped-slot').exists()).toBeTruthy();
    });

    it('should have content padding', async () => {
        const options = {
            propsData: {
                positionIdentifier: 'test',
                contentPadding: true,
            }
        };
        const wrapper = await createWrapper(options);

        expect(wrapper.find('.sw-card__content').classes('no--padding')).toBe(false);
    });

    it('should not have content padding', async () => {
        const options = {
            propsData: {
                positionIdentifier: 'test',
                contentPadding: false,
            }
        };
        const wrapper = await createWrapper(options);

        expect(wrapper.find('.sw-card__content').classes('no--padding')).toBe(true);
    });
});
