import { createLocalVue, shallowMount } from '@vue/test-utils';
import swPromotionV2EmptyStateHero from 'src/module/sw-promotion-v2/component/sw-promotion-v2-empty-state-hero';

Shopware.Component.register('sw-promotion-v2-empty-state-hero', swPromotionV2EmptyStateHero);

describe('src/module/sw-promotion-v2/component/sw-promotion-v2-empty-state-hero', () => {
    async function createWrapper(data = {}) {
        const localVue = createLocalVue();
        localVue.filter('asset', key => key);

        return shallowMount(await Shopware.Component.build('sw-promotion-v2-empty-state-hero'), {
            localVue,
            propsData: {
                title: 'Oh no, nothing was found.',
                description: 'I am some text, which is kinda small, but also somewhat longer than other texts!',
                ...data.propsData,
            },
            slots: data.slots,
            scopedSlots: data.scopedSlots,
        });
    }

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should render a title', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-promotion-v2-empty-state-hero__title').text())
            .toBe('Oh no, nothing was found.');
    });

    it('should render the module description', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-promotion-v2-empty-state-hero__description').text())
            .toBe('I am some text, which is kinda small, but also somewhat longer than other texts!');
    });

    it('should render no description, if `hideDescription` is active', async () => {
        const wrapper = await createWrapper({
            propsData: {
                hideDescription: true,
            },
        });

        expect(wrapper.find('.sw-promotion-v2-empty-state-hero__description').exists()).toBeFalsy();
    });

    it('should render no description, if there is no description text', async () => {
        const wrapper = await createWrapper({
            propsData: {
                description: null,
            },
        });

        expect(wrapper.find('.sw-promotion-v2-empty-state-hero__description').exists()).toBeFalsy();
    });

    it('should not render content of the actions slot, if not slotted', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-promotion-v2-empty-state-hero__actions').exists()).toBeFalsy();
    });

    it('should render content of the actions slot (unscoped)', async () => {
        const wrapper = await createWrapper({
            slots: {
                actions: '<button class="sw-button">BUY NOW!!!</button>',
            },
        });

        expect(wrapper.find('.sw-button').exists()).toBe(true);
    });

    it('should render content of the actions slot (scoped)', async () => {
        const wrapper = await createWrapper({
            scopedSlots: {
                actions: '<button class="sw-button">BUY NOW!!!</button>',
            },
        });

        expect(wrapper.find('.sw-button').exists()).toBe(true);
    });
});
