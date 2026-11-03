import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package after-sales
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-dashboard-index',
    load: () => import('./src/sw-dashboard-index'),
    public: {
        blocks: [
            'sw_dashboard_index',
            'sw_dashboard_index_content',
            'sw_dashboard_index_content_intro_card',
            'sw_dashboard_index_content_intro_content',
            'sw_dashboard_index_content_intro',
            'sw_dashboard_index_content_intro_content_headline',
            'sw_dashboard_index_content_intro_welcome_message',
            'sw_dashboard_index_content_info_grid',
            'sw_dashboard_index_content_info_grid_inner',
            'sw_dashboard_index_content_info__grid_inner_welcome_card',
            'sw_dashboard_index_content_info_grid_welcome_card_content',
            'sw_dashboard_index_content_info_grid_welcome_headline',
            'sw_dashboard_index_content_info_grid_welcome_link_list',
            'sw_dashboard_index_content_grid_info_inner_feedback_card',
            'sw_dashboard_index_content_info_grid_feedback_card_content',
            'sw_dashboard_index_content_feedback_headline',
            'sw_dashboard_index_content_feedback_text',
            'sw_dashboard_index_content_feedback_link',
        ],
        computed: [
            'welcomeMessage',
            'welcomeSubline',
            'greetingName',
        ],
        methods: [
            'createdComponent',
            'getGreetingTimeKey',
            'getGreetings',
        ],
    },
});
