import { CONTENT, TITLE, clickHeader, isOpen, mountMediaCollapse, unmountMediaCollapse } from './media-collapse.harness';

/**
 * @sw-package discovery
 */

/*
 * The media sidebar groups its quick actions and details into sections a merchant opens and closes by the title.
 * sw-media-collapse is such a section: it extends sw-collapse, which owns the open state and the content slot,
 * and adds the title and a chevron that shows whether the section is open.
 */
describe('sw-media-collapse', () => {
    afterEach(unmountMediaCollapse);

    /**
     * @requirement A merchant sees the title of each sidebar section, so they know what it holds before opening it.
     */
    it('shows the title of the section', async () => {
        // ACT
        const wrapper = await mountMediaCollapse({ title: 'Quick actions' });

        // ASSERT
        expect(wrapper.find(TITLE).text()).toBe('Quick actions');
    });

    /**
     * @requirement A merchant opens a closed section by clicking its header.
     * Sections start closed, so the sidebar stays short.
     */
    it('opens a closed section when the merchant clicks its header', async () => {
        // ARRANGE
        const wrapper = await mountMediaCollapse();
        expect(isOpen(wrapper)).toBe(false);

        // ACT
        await clickHeader(wrapper);

        // ASSERT
        expect(wrapper.find(CONTENT).text()).toBe('Content');
    });

    /**
     * @requirement A merchant closes an open section by clicking its header again.
     */
    it('closes an open section when the merchant clicks its header again', async () => {
        // ARRANGE
        const wrapper = await mountMediaCollapse();
        await clickHeader(wrapper);

        // ACT
        await clickHeader(wrapper);

        // ASSERT
        expect(isOpen(wrapper)).toBe(false);
    });

    /**
     * @requirement A section the sidebar needs at once, like the quick actions, is open when the merchant arrives.
     */
    it('starts open when the section is meant to be expanded on loading', async () => {
        // ACT
        const wrapper = await mountMediaCollapse({ expandOnLoading: true });

        // ASSERT
        expect(isOpen(wrapper)).toBe(true);
    });
});
