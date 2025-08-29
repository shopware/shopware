import Plugin from 'src/plugin-system/plugin.class';

/**
 * Affiliate tracking plugin that works with full-page cache environments.
 * Captures affiliate and campaign codes from URL parameters and stores them
 * in the user session via AJAX to ensure compatibility with cache layers like Varnish.
 *
 * @package framework
 */
export default class AffiliateTrackingPlugin extends Plugin {
    static options = {
        endpoint: '/affiliate-tracking',
    };

    init() {
        const params = this.getUrlParams();

        // Check if at least one tracking parameter is present and make the tracking request
        const processTracking = async () => {
            if (Object.keys(params).length > 0) {
                await this.makeRequest(params);
            }
        };
        // Emit event after processing is done, either if the request was made or skipped
        processTracking().finally(() => this.$emitter.publish('affiliateTrackingDone'));
    }

    /**
     * Extract affiliate and campaign tracking parameters from URL
     *
     * @returns {Object}
     */
    getUrlParams() {
        const params = new URLSearchParams(window.location.search);
        const affiliateCode = params.get('affiliateCode');
        const campaignCode = params.get('campaignCode');

        const extractedParams = {};
        if (affiliateCode) {
            extractedParams.affiliateCode = affiliateCode;
        }
        if (campaignCode) {
            extractedParams.campaignCode = campaignCode;
        }
        return extractedParams;
    }

    /**
     * Send data to backend endpoint via AJAX
     *
     * @param {Object} data
     */
    async makeRequest(data) {
        return fetch(this.options.endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data),
        })
            .then(response => {
                if (!response.ok) {
                    console.error('Affiliate capture failed:', response.statusText);
                }
            })
            .catch(error => {
                console.error('Error sending affiliate data:', error);
            });
    }
}
