/**
 * @package buyers-experience
 * @group disabledCompat
 */
import { mount } from '@vue/test-utils';

describe('src/module/sw-promotion-v2/component/sw-promotion-v2-empty-state-hero', () => {
    async function createWrapper(data = {}) {
        return mount(await wrapTestComponent('sw-promotion-v2-empty-state-hero', { sync: true }), {
            props: {
                title: 'Oh no, nothing was found.',
                description: 'I am some text, which is kinda small, but also somewhat longer than other texts!',
                ...data.props,
            },
        });
    }

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
            props: {
                hideDescription: true,
            },
        });

        expect(wrapper.find('.sw-promotion-v2-empty-state-hero__description').exists()).toBeFalsy();
    });

    it('should render no description, if there is no description text', async () => {
        const wrapper = await createWrapper({
            props: {
                description: null,
            },
        });

        expect(wrapper.find('.sw-promotion-v2-empty-state-hero__description').exists()).toBeFalsy();
    });
});
