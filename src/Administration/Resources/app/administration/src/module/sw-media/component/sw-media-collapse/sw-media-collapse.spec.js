/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';

async function createWrapper(props = {}, userConfigService = {}) {
    return mount(await wrapTestComponent('sw-media-collapse', { sync: true }), {
        props: {
            title: 'Meta data',
            expandOnLoading: true,
            ...props,
        },
        global: {
            provide: {
                userConfigService,
            },
        },
        slots: {
            content: '<div class="sw-media-collapse-test-content">Content</div>',
        },
    });
}

describe('module/sw-media/components/sw-media-collapse', () => {
    it('uses the default expanded state when no preference exists', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.expanded).toBe(true);
        expect(wrapper.find('.sw-media-collapse-test-content').exists()).toBe(true);
    });

    it('restores the stored expanded state', async () => {
        const wrapper = await createWrapper(
            {
                storageKey: 'metadata',
            },
            {
                search: jest.fn().mockResolvedValue({
                    data: {
                        'media.library.preferences': {
                            sidebarSections: {
                                metadata: false,
                            },
                        },
                    },
                }),
            },
        );
        await flushPromises();

        expect(wrapper.vm.expanded).toBe(false);
        expect(wrapper.find('.sw-media-collapse-test-content').exists()).toBe(false);
    });

    it('stores the expanded state when toggled', async () => {
        const userConfigService = {
            search: jest.fn().mockResolvedValue({
                data: {
                    'media.library.preferences': {
                        presentation: 'medium-preview',
                    },
                },
            }),
            upsert: jest.fn().mockResolvedValue(),
        };
        const wrapper = await createWrapper(
            {
                storageKey: 'metadata',
            },
            userConfigService,
        );

        await wrapper.find('.sw-collapse__header').trigger('click');
        await flushPromises();

        expect(wrapper.vm.expanded).toBe(false);
        expect(userConfigService.upsert).toHaveBeenCalledWith({
            'media.library.preferences': {
                presentation: 'medium-preview',
                sidebarSections: {
                    metadata: false,
                },
            },
        });
    });
});
