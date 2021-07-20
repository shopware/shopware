export default function initMarketing() {
    const marketingService = Shopware.Service('marketingService');

    marketingService.getActiveDiscountCampaigns().then((campaign) => {
        Shopware.State.commit('marketing/setCampaign', campaign);
    });
}
