import {
    LINK_ADVANCED_VISIBILITY,
    MODAL_ADVANCED_VISIBILITY,
    closeAdvancedVisibility,
    createVisibility,
    getProductVisibilities,
    getVisibilitySelect,
    mountProductVisibility,
    openAdvancedVisibility,
    removeInheritance,
    setUpProduct,
    unmountProductVisibility,
} from './product-visibility.harness';

/**
 * @sw-package inventory
 */

describe('sw-bulk-edit-product-visibility', () => {
    afterEach(unmountProductVisibility);

    it('hides the link to the visibility per sales channel while the product has no visibilities', async () => {
        // ARRANGE
        setUpProduct([]);

        // ACT
        const wrapper = await mountProductVisibility();

        // ASSERT
        expect(wrapper.find(LINK_ADVANCED_VISIBILITY).exists()).toBe(false);
    });

    it('shows no modal until the link is clicked', async () => {
        // ARRANGE
        setUpProduct([createVisibility('salesChannelA')]);

        // ACT
        const wrapper = await mountProductVisibility();

        // ASSERT
        expect(wrapper.find(MODAL_ADVANCED_VISIBILITY).exists()).toBe(false);
    });

    it('closes the modal when the modal closes itself', async () => {
        // ARRANGE
        setUpProduct([createVisibility('salesChannelA')]);
        const wrapper = await mountProductVisibility();
        await openAdvancedVisibility(wrapper);

        // ACT
        await closeAdvancedVisibility(wrapper);

        // ASSERT
        expect(wrapper.find(MODAL_ADVANCED_VISIBILITY).exists()).toBe(false);
    });

    it('opens the modal with the link even while disabled', async () => {
        // ARRANGE
        setUpProduct([createVisibility('salesChannelA')]);
        const wrapper = await mountProductVisibility({ disabled: true });

        // ACT
        await openAdvancedVisibility(wrapper);

        // ASSERT
        expect(wrapper.find(MODAL_ADVANCED_VISIBILITY).exists()).toBe(true);
    });

    it('passes no disabled attribute to the select while enabled', async () => {
        // ARRANGE
        setUpProduct([createVisibility('salesChannelA')]);

        // ACT
        const wrapper = await mountProductVisibility();

        // ASSERT
        expect(getVisibilitySelect(wrapper).props('disabled')).toBeUndefined();
        expect(wrapper.find(LINK_ADVANCED_VISIBILITY).classes()).not.toContain('is--disabled');
    });

    it('creates each own visibility with the visibility repository and copies the inherited fields onto it', async () => {
        // ARRANGE
        setUpProduct([createVisibility('salesChannelA')]);
        const wrapper = await mountProductVisibility();
        const inherited = {
            ...createVisibility('salesChannelB', 10),
            productId: 'parentId',
            productVersionId: 'parentVersionId',
        };

        // ACT
        await removeInheritance(wrapper, [inherited]);

        // ASSERT
        expect(getProductVisibilities()[1]).toEqual({
            createdBy: 'product_visibility',
            productId: 'productId',
            productVersionId: 'parentVersionId',
            salesChannelId: 'salesChannelB',
            salesChannel: { id: 'salesChannelB' },
            visibility: 10,
        });
    });

    it('returns the product visibilities and forces the inheritance off when the inheritance is removed', async () => {
        // ARRANGE
        setUpProduct([]);
        const wrapper = await mountProductVisibility();

        // ACT
        const { result, forceInheritanceRemove } = await removeInheritance(wrapper, [createVisibility('salesChannelA')]);

        // ASSERT
        expect(result).toBe(getProductVisibilities());
        expect(forceInheritanceRemove).toBe(true);
    });
});
