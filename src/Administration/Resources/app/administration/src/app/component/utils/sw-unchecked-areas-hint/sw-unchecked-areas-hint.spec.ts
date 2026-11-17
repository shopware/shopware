/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

async function createWrapper(labels: string[]) {
    return mount(await wrapTestComponent('sw-unchecked-areas-hint', { sync: true }), {
        props: { labels },
        global: {
            mocks: {
                $t: (key: string, params?: Record<string, string>) =>
                    params ? `${key} ${Object.values(params).join(' ')}` : `[${key}]`,
            },
        },
    });
}

describe('src/app/component/utils/sw-unchecked-areas-hint', () => {
    it('names every unchecked area in one translated sentence', async () => {
        const wrapper = await createWrapper([
            'sw-media.sidebar.usage.areaProducts',
            'sw-media.sidebar.usage.areaUsers',
        ]);

        expect(wrapper.find('.sw-unchecked-areas-hint').text()).toBe(
            'global.sw-unchecked-areas-hint.text [sw-media.sidebar.usage.areaProducts], [sw-media.sidebar.usage.areaUsers]',
        );
    });

    it('renders nothing without unchecked areas', async () => {
        const wrapper = await createWrapper([]);

        expect(wrapper.find('.sw-unchecked-areas-hint').exists()).toBe(false);
    });
});
