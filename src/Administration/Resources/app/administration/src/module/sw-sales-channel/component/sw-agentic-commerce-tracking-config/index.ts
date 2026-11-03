import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package discovery
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-agentic-commerce-tracking-config',
    load: () => import('./src/sw-agentic-commerce-tracking-config'),
    public: {
        props: [
            'salesChannel',
            'disabled',
        ],
        emits: [
            'change',
        ],
        blocks: [
            'sw_agentic_commerce_tracking_config',
            'sw_agentic_commerce_tracking_config_description',
            'sw_agentic_commerce_tracking_config_affiliate',
            'sw_agentic_commerce_tracking_config_campaign',
        ],
        computed: [
            'trackingConfig',
        ],
        methods: [
            'onAffiliateCodeChange',
            'onCampaignCodeChange',
        ],
    },
});
