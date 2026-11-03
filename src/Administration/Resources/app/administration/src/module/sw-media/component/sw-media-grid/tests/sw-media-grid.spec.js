/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';

describe('src/module/sw-media/component/sw-media-grid', () => {
    let wrapper;

    afterEach(() => {
        wrapper?.unmount();
        wrapper = null;
    });

    it('applies the presentation class', async () => {
        wrapper = mount(await wrapTestComponent('sw-media-grid', { sync: true }), {
            props: { presentation: 'list-preview' },
        });

        expect(wrapper.find('.sw-media-grid__content').classes()).toContain('sw-media-grid__presentation--list-preview');
    });

    it('does not emit anything on clicks outside of the grid', async () => {
        wrapper = mount(await wrapTestComponent('sw-media-grid', { sync: true }));

        window.dispatchEvent(new Event('click'));

        expect(wrapper.emitted()).toEqual({});
    });
});
