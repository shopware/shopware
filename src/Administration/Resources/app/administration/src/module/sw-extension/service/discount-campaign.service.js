export default class ShopwareDiscountCampaignService {
    isDiscountCampaignActive(discountCampaign) {
        if (!discountCampaign) {
            return false;
        }

        if (!discountCampaign.startDate) {
            return false;
        }

        const now = new Date();

        if (new Date(discountCampaign.startDate) > now) {
            return false;
        }

        if (typeof discountCampaign.endDate === 'string' &&
            new Date(discountCampaign.endDate) < now
        ) {
            return false;
        }

        if (typeof discountCampaign.discountAppliesForMonths === 'number' &&
            discountCampaign.discountAppliesForMonths === 0
        ) {
            return false;
        }

        // discounts without end date are always valid
        return true;
    }

    isSamePeriod(discountCampaign, comparator) {
        const discountDuration = discountCampaign.discountAppliesForMonths || null;
        const comparatorDuration = comparator.discountAppliesForMonths || null;

        return discountCampaign.startDate === comparator.startDate &&
            discountCampaign.endDate === comparator.endDate &&
            discountDuration === comparatorDuration;
    }
}
