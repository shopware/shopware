/**
 * @package services-settings
 */
import { mount } from '@vue/test-utils';
import 'src/app/component/rule/sw-arrow-field';

async function createWrapper(customOptions = {}) {
    return mount(await Shopware.Component.build('sw-arrow-field'), { ...customOptions });
}


describe('src/app/component/rule/sw-arrow-field', () => {
    it('should have enabled links', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.classes()).not.toContain('is--disabled');
    });

    it('should have disabled links', async () => {
        const wrapper = await createWrapper({
            props: {
                disabled: true,
            },
        });

        expect(wrapper.classes()).toContain('is--disabled');
    });

    it('renders custom slot', async () => {
        const wrapper = await createWrapper({
            slots: {
                default: '<div class="component-inside-wrapper"></div>',
            },
        });

        expect(wrapper.get('.component-inside-wrapper').exists()).toBe(true);
    });

    it('has a transparent fill color by default', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.get('path').attributes('fill')).toBe('transparent');
    });

    it('can override fill color', async () => {
        const wrapper = await createWrapper({
            props: {
                primary: '#00ff00',
            },
        });

        expect(wrapper.get('path').attributes('fill')).toBe('#00ff00');
    });
});
