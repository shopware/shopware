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

        // Check if at least one tracking parameter is present
        const paramValues = Object.values(params);
        for (const paramValue of paramValues) {
            if (paramValue !== null) {
                this.makeRequest(params);
                break;
            }
        }
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

        return {
            affiliateCode,
            campaignCode,
        };
    }

    /**
     * Send data to backend endpoint via AJAX
     *
     * @param {Object} data
     */
    makeRequest(data) {
        fetch(this.options.endpoint, {
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
