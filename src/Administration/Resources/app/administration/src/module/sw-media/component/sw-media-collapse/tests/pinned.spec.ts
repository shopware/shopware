import {
    TITLE,
    getShownChevrons,
    isOpen,
    clickHeader,
    mountMediaCollapse,
    pressEnterOnHeader,
    unmountMediaCollapse,
} from './media-collapse.harness';

/**
 * @sw-package discovery
 */

describe('sw-media-collapse', () => {
    afterEach(unmountMediaCollapse);

    it('shows the chevron pointing right while the section is closed', async () => {
        // ACT
        const wrapper = await mountMediaCollapse();

        // ASSERT
        expect(getShownChevrons(wrapper)).toEqual(['regular-chevron-right-xs']);
    });

    it('shows the chevron pointing down while the section is open', async () => {
        // ARRANGE
        const wrapper = await mountMediaCollapse();

        // ACT
        await clickHeader(wrapper);

        // ASSERT
        expect(getShownChevrons(wrapper)).toEqual(['regular-chevron-down-xs']);
    });

    it('shows the chevron pointing down when it starts open', async () => {
        // ACT
        const wrapper = await mountMediaCollapse({ expandOnLoading: true });

        // ASSERT
        expect(getShownChevrons(wrapper)).toEqual(['regular-chevron-down-xs']);
    });

    it('opens and closes the section with Enter on the header', async () => {
        // ARRANGE
        const wrapper = await mountMediaCollapse();

        // ACT
        await pressEnterOnHeader(wrapper);
        const openAfterFirstEnter = isOpen(wrapper);
        await pressEnterOnHeader(wrapper);

        // ASSERT
        expect(openAfterFirstEnter).toBe(true);
        expect(isOpen(wrapper)).toBe(false);
    });

    it('marks the header as a focusable button', async () => {
        // ACT
        const wrapper = await mountMediaCollapse();

        // ASSERT
        expect(wrapper.find('.sw-collapse__header').attributes()).toMatchObject({ role: 'button', tabindex: '0' });
    });

    it('replaces the title and chevrons with the header slot, which gets the open state', async () => {
        // ARRANGE
        const wrapper = await mountMediaCollapse(
            {},
            { header: '<template #header="{ expanded }"><span class="own-header">{{ expanded }}</span></template>' },
        );

        // ACT
        await clickHeader(wrapper);

        // ASSERT
        expect(wrapper.find(TITLE).exists()).toBe(false);
        expect(getShownChevrons(wrapper)).toEqual([]);
        expect(wrapper.find('.own-header').text()).toBe('true');
    });
});
