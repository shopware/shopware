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
    name: 'sw-flow-detail',
    load: () => import('./src/sw-flow-detail'),
    public: {
        props: [
            'flowId',
        ],
        blocks: [
            'sw_flow_detail',
            'sw_flow_detail_header',
            'sw_flow_detail_smart_bar_actions',
            'sw_flow_detail_smart_bar_actions_save',
            'sw_flow_content',
            'sw_flow_leave_page_modal',
            'sw_flow_tabs_header',
            'sw_flow_tabs_header_mt_tabs',
            'sw_flow_tabs_header_general',
            'sw_flow_tabs_header_builder',
            'sw_flow_tabs_header_extension',
            'sw_flow_tabs_content',
            'sw_flow_detail_modal_extension',
        ],
        computed: [
            'identifier',
            'flowRepository',
            'flowTemplateRepository',
            'flowSequenceRepository',
            'appFlowActionRepository',
            'isNewFlow',
            'flowCriteria',
            'flowTemplateCriteria',
            'documentTypeRepository',
            'documentTypeCriteria',
            'mailTemplateRepository',
            'customFieldSetRepository',
            'customFieldRepository',
            'mailTemplateIdsCriteria',
            'customerGroupRepository',
            'customerGroupCriteria',
            'appFlowActionCriteria',
            'stateMachineStateRepository',
            'stateMachineStateCriteria',
            'customFieldSetCriteria',
            'customFieldCriteria',
            'ruleRepository',
            'isTemplate',
            'isUnknownTrigger',
            'flowDetailTabs',
        ],
        methods: [
            'createdComponent',
            'beforeDestroyComponent',
            'routeDetailTab',
            'createNewFlow',
            'getDetailFlow',
            'getAppFlowAction',
            'getDetailFlowTemplate',
            'onSave',
            'updateSequences',
            'getDeletedSequenceIds',
            'handleFieldValiationError',
            'saveFinish',
            'onLeaveModalClose',
            'onLeaveModalConfirm',
            'removeAllSelectors',
            'validateEmptySequence',
            'getDataForActionDescription',
            'createFromFlowTemplate',
            'createSequenceEntity',
            'buildSequencesFromConfig',
            'getRuleDataForFlowTemplate',
        ],
    },
});
