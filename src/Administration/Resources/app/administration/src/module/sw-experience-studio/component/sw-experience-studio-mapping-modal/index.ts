import type { ContentSystemMappingCandidate } from 'src/core/service/api/content-system-mapping-candidate.api.service';
import { getMappingCandidateTranslation, groupCandidates } from '../../util/element-mapping.util';
import template from './sw-experience-studio-mapping-modal.html.twig';
import './sw-experience-studio-mapping-modal.scss';

const GROUP_SNIPPET_ROOT = 'sw-experience-studio.detail.elementSettings.mapping.groups';
const SOURCE_SNIPPET_ROOT = 'sw-experience-studio.detail.elementSettings.mapping.sources';

type MappingSourceGroup = {
    key: string;
    type: string;
    id: string;
    candidates: ContentSystemMappingCandidate[];
};

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
            selectedSourceKey: null as string | null,
        };
    },

    computed: {
        matchingCandidates(): ContentSystemMappingCandidate[] {
            const term = this.searchTerm.trim().toLowerCase();

            if (term.length === 0) {
                return this.candidates;
            }

            return this.candidates.filter((candidate) => {
                const sourceLabel = this.getSourceLabel(candidate.source.type, candidate.source.id).toLowerCase();

                return (
                    this.getCandidateLabel(candidate).toLowerCase().includes(term) ||
                    candidate.path.toLowerCase().includes(term) ||
                    sourceLabel.includes(term)
                );
            });
        },

        sourceGroups(): MappingSourceGroup[] {
            const groups = new Map<string, MappingSourceGroup>();

            for (const candidate of this.matchingCandidates) {
                const key = this.getSourceKey(candidate);
                const existingGroup = groups.get(key);

                if (existingGroup) {
                    existingGroup.candidates.push(candidate);
                    continue;
                }

                groups.set(key, {
                    key,
                    type: candidate.source.type,
                    id: candidate.source.id,
                    candidates: [candidate],
                });
            }

            return Array.from(groups.values()).sort((left, right) => {
                if (left.type === 'root' && right.type !== 'root') {
                    return -1;
                }

                if (right.type === 'root' && left.type !== 'root') {
                    return 1;
                }

                return 0;
            });
        },

        activeSourceGroup(): MappingSourceGroup | null {
            if (this.sourceGroups.length === 0) {
                return null;
            }

            const selected = this.sourceGroups.find((group) => group.key === this.selectedSourceKey);
            if (selected) {
                return selected;
            }

            const current = this.sourceGroups.find((group) => group.candidates.some((candidate) => candidate.path === this.currentPath));
            if (current) {
                return current;
            }

            return this.sourceGroups[0];
        },

        candidateGroups(): Array<{ group: string; candidates: ContentSystemMappingCandidate[] }> {
            return groupCandidates(this.activeSourceGroup?.candidates ?? []);
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
        getSourceKey(candidate: ContentSystemMappingCandidate): string {
            return `${candidate.source.type}:${candidate.source.id}`;
        },

        getSourceLabel(type: string, id: string): string {
            if (type === 'root') {
                const entityLabelKey = `sw-experience-studio.createWizard.layoutTypes.${id}`;
                const entityLabel = this.$te(entityLabelKey) ? this.$t(entityLabelKey) : id;

                return `${this.$t(`${SOURCE_SNIPPET_ROOT}.rootEntity`)} · ${entityLabel}`;
            }

            if (type === 'context' && id === 'storefront') {
                return this.$t(`${SOURCE_SNIPPET_ROOT}.storefrontContext`);
            }

            return `${type}: ${id}`;
        },

        isSelectedSource(group: MappingSourceGroup): boolean {
            return this.activeSourceGroup?.key === group.key;
        },

        onSelectSource(group: MappingSourceGroup): void {
            this.selectedSourceKey = group.key;
        },

        /**
         * Static catalogue labels are snippet keys. Dynamic candidates such as custom fields carry their own
         * localized configuration and prefer it over snippets.
         */
        getCandidateLabel(candidate: ContentSystemMappingCandidate): string {
            const configured = getMappingCandidateTranslation(
                candidate.labelTranslations,
                Shopware.Store.get('session').currentLocale ?? '',
                Shopware.Context.app.fallbackLocale ?? '',
            );

            if (configured !== '') {
                return configured;
            }

            return this.$te(candidate.label) ? this.$t(candidate.label) : candidate.label;
        },

        getCandidateDescription(candidate: ContentSystemMappingCandidate): string {
            const configured = getMappingCandidateTranslation(
                candidate.descriptionTranslations,
                Shopware.Store.get('session').currentLocale ?? '',
                Shopware.Context.app.fallbackLocale ?? '',
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
