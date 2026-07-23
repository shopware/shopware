/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

async function createWrapper(props = {}, userConfig: { data: Record<string, unknown> } = { data: {} }) {
    const search = jest.fn().mockResolvedValue(userConfig);
    const upsert = jest.fn().mockResolvedValue(undefined);

    const wrapper = mount(await wrapTestComponent('sw-dismissible-notices', { sync: true }), {
        props: {
            notices: [
                { key: 'a.b.first', deprecationVersion: 'v6.8.0.0' },
                { key: 'a.b.second', deprecationVersion: 'v6.8.0.0' },
            ],
            ...props,
        },
        global: {
            mocks: {
                $t: (path: string) => path,
            },
            stubs: {
                'mt-banner': {
                    template: '<div class="mt-banner" @click="$emit(\'close\')"><slot></slot></div>',
                },
            },
            provide: {
                userConfigService: { search, upsert },
            },
        },
    });

    return { wrapper, search, upsert };
}

describe('src/app/component/utils/sw-dismissible-notices', () => {
    let isActiveSpy: jest.SpyInstance;

    beforeEach(() => {
        isActiveSpy = jest.spyOn(Shopware.Feature, 'isActive').mockReturnValue(false);
    });

    afterEach(() => {
        isActiveSpy.mockRestore();
    });

    it('renders a banner for each notice and requests the dismissed notices', async () => {
        const { wrapper, search } = await createWrapper();
        await flushPromises();

        expect(search).toHaveBeenCalledWith(['core.dismissedNotices']);
        expect(wrapper.findAll('.mt-banner')).toHaveLength(2);
    });

    it('hides notices that were already dismissed', async () => {
        const { wrapper } = await createWrapper({}, { data: { 'core.dismissedNotices': ['a.b.first'] } });
        await flushPromises();

        const banners = wrapper.findAll('.mt-banner');
        expect(banners).toHaveLength(1);
        expect(banners[0].text()).toBe('a.b.second');
    });

    it('persists the dismissed notice and hides it on close', async () => {
        const { wrapper, upsert } = await createWrapper();
        await flushPromises();

        await wrapper.findAll('.mt-banner')[0].trigger('click');
        await flushPromises();

        expect(upsert).toHaveBeenCalledWith({
            'core.dismissedNotices': ['a.b.first'],
        });
        expect(wrapper.findAll('.mt-banner')).toHaveLength(1);
    });

    it('hides notices whose removal version is already active', async () => {
        isActiveSpy.mockImplementation((version: string) => version === 'v6.8.0.0');

        const { wrapper } = await createWrapper({
            notices: [
                { key: 'a.b.first', deprecationVersion: 'v6.8.0.0' },
                { key: 'a.b.second', deprecationVersion: 'v6.9.0.0' },
            ],
        });
        await flushPromises();

        const banners = wrapper.findAll('.mt-banner');
        expect(banners).toHaveLength(1);
        expect(banners[0].text()).toBe('a.b.second');
    });
});
