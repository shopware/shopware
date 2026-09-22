import type { ContentSystemMappingCandidate } from 'src/core/service/api/content-system-mapping-candidate.api.service';
import { getMappingCandidateTranslation, groupCandidates } from '../../util/element-mapping.util';
import template from './sw-experience-studio-mapping-modal.html.twig';
import './sw-experience-studio-mapping-modal.scss';

const GROUP_SNIPPET_ROOT = 'sw-experience-studio.detail.elementSettings.mapping.groups';

/**
 * @private
 * @sw-package discovery
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        candidates: {
            type: Array as PropType<ContentSystemMappingCandidate[]>,
            required: true,
        },
        propertyTitle: {
            type: String,
            required: false,
            default: '',
        },
        currentPath: {
            type: String,
            required: false,
            default: null,
        },
    },

    emits: [
        'select',
        'close',
    ],

    data() {
        return {
            searchTerm: '',
        };
    },

    computed: {
        matchingCandidates(): ContentSystemMappingCandidate[] {
            const term = this.searchTerm.trim().toLowerCase();

            if (term.length === 0) {
                return this.candidates;
            }

            return this.candidates.filter((candidate) => {
                return (
                    this.getCandidateLabel(candidate).toLowerCase().includes(term) ||
                    candidate.path.toLowerCase().includes(term)
                );
            });
        },

        candidateGroups(): Array<{ group: string; candidates: ContentSystemMappingCandidate[] }> {
            return groupCandidates(this.matchingCandidates);
        },

        hasCandidates(): boolean {
            return this.candidates.length > 0;
        },

        emptyStateDescription(): string {
            return this.hasCandidates
                ? this.$t('sw-experience-studio.detail.elementSettings.mapping.noSearchResults')
                : this.$t('sw-experience-studio.detail.elementSettings.mapping.noCandidates');
        },
    },

    methods: {
        /**
         * Static catalogue labels are snippet keys. Dynamic candidates such as custom fields carry their own
         * localized configuration and prefer it over snippets.
         */
        getCandidateLabel(candidate: ContentSystemMappingCandidate): string {
            const configured = getMappingCandidateTranslation(
                candidate.labelTranslations,
                Shopware.Store.get('session').currentLocale,
                Shopware.Context.app.fallbackLocale,
            );

            if (configured !== '') {
                return configured;
            }

            return this.$te(candidate.label) ? this.$t(candidate.label) : candidate.label;
        },

        getCandidateDescription(candidate: ContentSystemMappingCandidate): string {
            const configured = getMappingCandidateTranslation(
                candidate.descriptionTranslations,
                Shopware.Store.get('session').currentLocale,
                Shopware.Context.app.fallbackLocale,
            );

            if (configured !== '') {
                return configured;
            }

            return this.$te(candidate.description) ? this.$t(candidate.description) : '';
        },

        getGroupLabel(group: string): string {
            const key = `${GROUP_SNIPPET_ROOT}.${group}`;

            return this.$te(key) ? this.$t(key) : group;
        },

        isSelectedCandidate(candidate: ContentSystemMappingCandidate): boolean {
            return this.currentPath === candidate.path;
        },

        onSelectCandidate(candidate: ContentSystemMappingCandidate): void {
            this.$emit('select', candidate);
        },

        onClose(): void {
            this.$emit('close');
        },
    },
});
