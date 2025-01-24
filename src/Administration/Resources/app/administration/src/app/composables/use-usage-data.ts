/**
 * @sw-package framework
 */
import { ref } from 'vue';
import type { UsageDataContext } from 'src/core/service/api/usage-data.api.service';

const isConsentGiven = ref(false);
const isBannerHidden = ref(false);

function resetConsent() {
    isConsentGiven.value = false;
    isBannerHidden.value = true;
}

function updateConsent(context: UsageDataContext) {
    isConsentGiven.value = context.isConsentGiven;
    isBannerHidden.value = context.isBannerHidden;
}

function updateIsConsentGiven(newIsConsentGiven: boolean) {
    isConsentGiven.value = newIsConsentGiven;
}

function hideBanner() {
    isBannerHidden.value = true;
}

function $reset() {
    isConsentGiven.value = false;
    isBannerHidden.value = false;
}

/**
 * @private
 */
export default function useUsageData() {
    return {
        isConsentGiven,
        isBannerHidden,
        resetConsent,
        updateConsent,
        updateIsConsentGiven,
        hideBanner,
        $reset,
    };
}
