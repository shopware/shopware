import {
    LINK_ADVANCED_VISIBILITY,
    MODAL_ADVANCED_VISIBILITY,
    applyAdvancedVisibility,
    createVisibility,
    getProductVisibilities,
    getVisibilitySelect,
    mountProductVisibility,
    openAdvancedVisibility,
    removeInheritance,
    selectVisibilities,
    setUpProduct,
    unmountProductVisibility,
} from './product-visibility.harness';

/**
 * @sw-package inventory
 */

/*
 * The visibility field of the product bulk edit: in which sales channels the edited products are shown.
 * The product being edited comes from the product detail store, which the bulk edit fills.
 * A modal sets the visibility per sales channel, and removing the inheritance turns inherited visibilities into own ones.
 */
describe('sw-bulk-edit-product-visibility', () => {
    afterEach(unmountProductVisibility);

    /**
     * @requirement A merchant sees the sales channels the products are visible in.
     */
    it('shows the visibilities of the product in the select', async () => {
        // ARRANGE
        setUpProduct([createVisibility('salesChannelA')]);

        // ACT
        const wrapper = await mountProductVisibility();

        // ASSERT
        expect(getVisibilitySelect(wrapper).props('entityCollection')).toEqual(getProductVisibilities());
    });

    /**
     * @requirement A merchant chooses the sales channels the products become visible in.
     */
    it('changes the visibilities of the product', async () => {
        // ARRANGE
        setUpProduct([createVisibility('salesChannelA')]);
        const wrapper = await mountProductVisibility();
        const chosen = [createVisibility('salesChannelA'), createVisibility('salesChannelB')];

        // ACT
        await selectVisibilities(wrapper, chosen);

        // ASSERT
        expect(getProductVisibilities()).toEqual(chosen);
    });

    /**
     * @requirement A merchant sets the visibility per sales channel in a modal and applies it.
     * One sales channel may list the products everywhere, another only through search or a direct link.
     */
    it('opens and applies the visibility per sales channel', async () => {
        // ARRANGE
        setUpProduct([createVisibility('salesChannelA')]);
        const wrapper = await mountProductVisibility();

        // ACT
        await openAdvancedVisibility(wrapper);
        const openedModal = wrapper.find(MODAL_ADVANCED_VISIBILITY).exists();
        await applyAdvancedVisibility(wrapper);

        // ASSERT
        expect(openedModal).toBe(true);
        expect(wrapper.find(MODAL_ADVANCED_VISIBILITY).exists()).toBe(false);
    });

    /**
     * @requirement A merchant who removes the inheritance keeps the inherited sales channels as own visibilities.
     * The products should stay visible where they were until the merchant changes it.
     */
    it('turns the inherited visibilities into own ones when the inheritance is removed', async () => {
        // ARRANGE
        setUpProduct([]);
        const wrapper = await mountProductVisibility();

        // ACT
        await removeInheritance(wrapper, [createVisibility('salesChannelA', 10)]);

        // ASSERT
        expect(getProductVisibilities().map((visibility) => visibility.salesChannelId)).toEqual(['salesChannelA']);
    });

    /**
     * @requirement A merchant cannot change the visibilities while the field is disabled.
     * The bulk edit disables a field until the merchant chooses to change it.
     */
    it('disables the select and the link to the visibility per sales channel', async () => {
        // ARRANGE
        setUpProduct([createVisibility('salesChannelA')]);

        // ACT
        const wrapper = await mountProductVisibility({ disabled: true });

        // ASSERT
        expect(getVisibilitySelect(wrapper).props('disabled')).toBe(true);
        expect(wrapper.find(LINK_ADVANCED_VISIBILITY).classes()).toContain('is--disabled');
    });
});
