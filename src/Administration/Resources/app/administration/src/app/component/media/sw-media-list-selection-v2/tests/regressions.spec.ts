import { mount, type VueWrapper } from '@vue/test-utils';
import { reactive } from 'vue';
import type { PublicInstance } from 'src/core/public-component';
import type SwMediaListSelectionV2 from '../index';

/**
 * @sw-package discovery
 */

type MediaItem = { id: string; url: string };

type DraggableElement = HTMLElement & { dragConfig: { data: { position: number } } };

const ITEM = '.sw-media-list-selection-item-v2';

const first = { id: '1', url: 'http://shopware.com/image1.jpg' };
const second = { id: '2', url: 'http://shopware.com/image2.jpg' };
const third = { id: '3', url: 'http://shopware.com/image3.jpg' };

async function mountMediaList(
    entityMediaItems: MediaItem[],
): Promise<VueWrapper<PublicInstance<typeof SwMediaListSelectionV2>>> {
    const wrapper = mount(await wrapTestComponent('sw-media-list-selection-v2', { sync: true }), {
        props: {
            entity: {},
            entityMediaItems: reactive(entityMediaItems),
        },
        global: {
            provide: {
                mediaService: {},
            },
            stubs: {
                'sw-media-upload-v2': true,
                'sw-media-list-selection-item-v2': await wrapTestComponent('sw-media-list-selection-item-v2'),
                'sw-media-preview-v2': {
                    props: ['source'],
                    template: '<div class="sw-media-preview-v2">{{ source }}</div>',
                },
                'sw-context-button': true,
                'sw-context-menu-item': true,
                'sw-loader': true,
            },
        },
    });
    await flushPromises();

    return wrapper as never;
}

async function showMediaItems(wrapper: VueWrapper, entityMediaItems: MediaItem[]): Promise<void> {
    await wrapper.setProps({ entityMediaItems: reactive(entityMediaItems) });
    await flushPromises();
}

describe('sw-media-list-selection-v2', () => {
    /**
     * @regression #13909 After a merchant reordered a list that holds the same image twice,
     * the list showed the wrong copy in the wrong slot.
     */
    it('shows the same media added twice as two items after a reorder', async () => {
        // ARRANGE
        const wrapper = await mountMediaList([
            second,
            first,
            third,
            first,
        ]);

        // ACT
        await showMediaItems(wrapper, [
            first,
            third,
            first,
            second,
        ]);

        // ASSERT
        const shown = wrapper
            .findAll(ITEM)
            .slice(0, 4)
            .map((item) => item.text());
        expect(shown).toEqual([
            '1',
            '3',
            '1',
            '2',
        ]);
    });

    /**
     * @regression #13909 Dragging the first of two copies of an image moved the last copy,
     * because both copies shared one drag position.
     */
    it('gives each copy of the same media its own drag position', async () => {
        // ARRANGE
        const wrapper = await mountMediaList([first, second, first]);

        // ACT
        const positions = wrapper
            .findAll(ITEM)
            .slice(0, 3)
            .map((item) => (item.element as DraggableElement).dragConfig.data.position);

        // ASSERT
        expect(positions).toEqual([0, 1, 2]);
    });
});
