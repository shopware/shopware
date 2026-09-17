import type { ContentSystemMappingCandidate } from 'src/core/service/api/content-system-mapping-candidate.api.service';
import { groupCandidates } from '../../util/element-mapping.util';
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
         * Catalogue labels are snippet keys, but an app or plugin may ship a candidate whose snippet is missing.
         * Falling back to the key keeps the entry pickable instead of rendering an empty row.
         */
        getCandidateLabel(candidate: ContentSystemMappingCandidate): string {
            return this.$te(candidate.label) ? this.$t(candidate.label) : candidate.label;
        },

        getCandidateDescription(candidate: ContentSystemMappingCandidate): string {
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
