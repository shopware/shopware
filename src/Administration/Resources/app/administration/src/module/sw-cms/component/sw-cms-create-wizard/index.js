import template from './sw-cms-create-wizard.html.twig';
import './sw-cms-create-wizard.scss';

const { Filter } = Shopware;

/**
 * @package content
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'feature',
        'cmsPageTypeService',
        'customEntityDefinitionService',
    ],

    props: {
        page: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            step: 1,
            steps: {
                pageType: 1,
                sectionType: 2,
                pageName: 3,
            },
        };
    },

    computed: {
        visiblePageTypes() {
            return this.cmsPageTypeService.getVisibleTypes();
        },

        currentPageType() {
            return this.cmsPageTypeService.getType(this.page.type);
        },

        isCustomEntityType() {
            return this.page.type.startsWith('custom_entity_');
        },

        isCompletable() {
            return [
                this.page.name,
                !this.isCustomEntityType || this.page.entity,
            ].every(condition => condition);
        },

        customEntities() {
            return this.customEntityDefinitionService.getCmsAwareDefinitions().map((entity) => {
                const snippetKey = `${entity.entity}.moduleTitle`;
                const value = entity.entity;

                return {
                    value,
                    label: this.$te(snippetKey) ? this.$tc(snippetKey) : value,
                };
            });
        },

        pagePreviewMedia() {
            if (this.page.sections.length < 1) {
                return '';
            }

            const imgPath = 'administration/static/img/cms';

            return `url(${this.assetFilter(`${imgPath}/preview_${this.page.type}_${this.page.sections[0].type}.png`)})`;
        },

        pagePreviewStyle() {
            return {
                'background-image': this.pagePreviewMedia,
                'background-size': 'cover',
            };
        },

        assetFilter() {
            return Filter.getByName('asset');
        },
    },

    watch: {
        step(newStep) {
            if (this.getStepName(newStep) === 'sectionType') {
                this.page.sections = [];
            }
        },
    },

    methods: {
        goToStep(stepName) {
            this.step = this.steps[stepName];
        },

        getStepName(stepValue) {
            const find = Object.entries(this.steps).find((step) => {
                return stepValue === step[1];
            });

            if (!find) {
                return '';
            }

            return find[0];
        },

        onPageTypeSelect(type) {
            Shopware.State.commit('cmsPageState/setCurrentPageType', type);
            this.page.type = type;

            this.goToStep('sectionType');
        },

        onSectionSelect(section) {
            this.goToStep('pageName');

            this.$emit('on-section-select', section);
        },

        onCompletePageCreation() {
            if (!this.page.name) {
                return;
            }

            this.$emit('wizard-complete');
        },
    },
};
