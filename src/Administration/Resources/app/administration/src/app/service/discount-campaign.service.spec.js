import ShopwareDiscountCampaignService from 'src/app/service/discount-campaign.service';

/**
 * @package services-settings
 * @group disabledCompat
 */
describe('src/app/service/discount-campaign.service.ts', () => {
    beforeAll(() => {
        jest.useFakeTimers('modern');
        jest.setSystemTime(new Date('4-22-2022 12:00:00'));
    });

    afterAll(() => {
        jest.useRealTimers();
    });

    const shopwareDiscountCampaignService = new ShopwareDiscountCampaignService();

    describe('isDiscountCampaignActive', () => {
        it('returns true if today is between startDate and endDate and no duration is set', async () => {
            const campaign = {
                startDate: '04-21-2022 00:00:00',
                endDate: '04-23-2022 00:00:00',
            };

            expect(shopwareDiscountCampaignService.isDiscountCampaignActive(campaign)).toBe(true);
        });

        it('returns true if today is between startDate and endDate and duration > 0 is set', async () => {
            const campaign = {
                startDate: '04-21-2022 00:00:00',
                endDate: '04-23-2022 00:00:00',
                discountAppliesForMonths: 10,
            };

            expect(shopwareDiscountCampaignService.isDiscountCampaignActive(campaign)).toBe(true);
        });

        it('returns false if discountCampaign is null', async () => {
            expect(shopwareDiscountCampaignService.isDiscountCampaignActive(null)).toBe(false);
        });

        it('returns false if discountCampaign has no startDate', async () => {
            expect(shopwareDiscountCampaignService.isDiscountCampaignActive({ startDate: null }))
                .toBe(false);
        });

        it('returns false if startDate is in the future', async () => {
            const campaign = {
                startDate: '04-23-2022 00:00:00',
                endDate: '04-26-2022 00:00:00',
                discountAppliesForMonths: 10,
            };

            expect(shopwareDiscountCampaignService.isDiscountCampaignActive(campaign)).toBe(false);
        });

        it('returns false if endDate is in the past', async () => {
            const campaign = {
                startDate: '04-20-2022 00:00:00',
                endDate: '04-22-2022 00:00:00',
                discountAppliesForMonths: 10,
            };

            expect(shopwareDiscountCampaignService.isDiscountCampaignActive(campaign)).toBe(false);
        });

        it('returns false if discountDuration is 0', async () => {
            const campaign = {
                startDate: '04-21-2022 00:00:00',
                endDate: '04-23-2022 00:00:00',
                discountAppliesForMonths: 0,
            };

            expect(shopwareDiscountCampaignService.isDiscountCampaignActive(campaign)).toBe(false);
        });
    });

    describe('isSamePeriod', () => {
        const originalCampaign = {
            startDate: '04-21-2022 00:00:00',
            endDate: '04-23-2022 00:00:00',
            discountAppliesForMonths: 0,
        };

        const differences = [
            [
                'startDate', {
                    startDate: '04-05-2022 00:00:00',
                    endDate: originalCampaign.endDate,
                    discountAppliesForMonths: originalCampaign.discountAppliesForMonths,
                },
            ], [
                'endDate', {
                    startDate: originalCampaign.startDate,
                    endDate: '05-01-2022 00:00:00',
                    discountAppliesForMonths: originalCampaign.discountAppliesForMonths,
                },
            ], [
                'discountAppliesForMonths', {
                    startDate: originalCampaign.startDate,
                    endDate: originalCampaign.endDate,
                    discountAppliesForMonths: 5,
                },
            ],
        ];

        it('returns true if startDate, endDate and discountAppliesForMonths are the same', async () => {
            expect(shopwareDiscountCampaignService.isSamePeriod(originalCampaign, originalCampaign)).toBe(true);
        });

        it.each(differences)('returns false if %s is different', (propName, differentCampaign) => {
            expect(shopwareDiscountCampaignService.isSamePeriod(originalCampaign, differentCampaign)).toBe(false);
        });
    });
});
