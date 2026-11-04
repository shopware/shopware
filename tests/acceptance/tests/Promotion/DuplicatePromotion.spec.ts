import { test, expect, translate } from '@fixtures/AcceptanceTest';

test(
    'Shop administrator should be able to duplicate a promotion as an inactive copy.',
    { tag: '@Promotion' },
    async ({
        ShopAdmin,
        IdProvider,
        TestDataService,
        AdminApiContext,
        AdminPromotionsListing,
        AdminPromotionDetail,
        DuplicatePromotion,
    }) => {
        const promotion = await TestDataService.createPromotionWithCode({
            name: `ClonePromo-${IdProvider.getIdPair().uuid}`,
            active: true,
        });
        const copiedName = `${promotion.name} ${translate('administration:promotion:actions.copy')}`;

        await test.step('Duplicate the promotion from the promotion listing.', async () => {
            await ShopAdmin.goesTo(AdminPromotionsListing.url([promotion.name]));
            await ShopAdmin.attemptsTo(DuplicatePromotion(promotion.name));
            await ShopAdmin.expects(AdminPromotionDetail.nameInput).toHaveValue(copiedName);
        });

        await test.step('Verify the copy is inactive, has no code, and keeps the discount.', async () => {
            const response = await AdminApiContext.post('search/promotion', {
                data: {
                    filter: [{ type: 'equalsAny', field: 'name', value: [promotion.name, copiedName] }],
                    associations: { discounts: {} },
                },
            });
            expect(response.ok()).toBeTruthy();
            const promotions = (await response.json()).data;
            const clone = promotions.find(({ name }) => name === copiedName);
            const source = promotions.find(({ name }) => name === promotion.name);

            expect(clone.active).toBe(false);
            expect(clone.code).toBeNull();
            expect(clone.useCodes).toBe(false);
            expect(clone.discounts).toHaveLength(1);
            expect(clone.discounts[0].id).not.toBe(source.discounts[0].id);
            expect(clone.discounts[0].value).toBe(source.discounts[0].value);
            expect(clone.discounts[0].type).toBe(source.discounts[0].type);
            expect(source.active).toBe(true);
            expect(source.code).toBe(promotion.code);
        });
    },
);
