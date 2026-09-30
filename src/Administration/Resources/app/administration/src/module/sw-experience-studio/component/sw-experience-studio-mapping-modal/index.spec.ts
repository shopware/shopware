import type { ContentSystemMappingCandidate } from 'src/core/service/api/content-system-mapping-candidate.api.service';
import mappingModalComponent from './index';

function candidate(overrides: Partial<ContentSystemMappingCandidate> = {}): ContentSystemMappingCandidate {
    return {
        path: 'category.name',
        source: { type: 'root', id: 'category', path: 'name' },
        label: 'sw-experience-studio.mapping.category.name.label',
        description: 'sw-experience-studio.mapping.category.name.description',
        group: 'basic',
        valueType: 'string',
        contextType: 'single',
        projection: null,
        ...overrides,
    };
}

describe('module/sw-experience-studio/component/sw-experience-studio-mapping-modal', () => {
    const computed = (
        mappingModalComponent as unknown as {
            computed: Record<string, (...args: unknown[]) => unknown>;
        }
    ).computed;
    const methods = (
        mappingModalComponent as unknown as {
            methods: Record<string, (...args: unknown[]) => unknown>;
        }
    ).methods;

    const translations: Record<string, string> = {
        'sw-experience-studio.mapping.category.name.label': 'Category name',
        'sw-experience-studio.mapping.category.name.description': 'The name of the category the page shows.',
        'sw-experience-studio.detail.elementSettings.mapping.groups.basic': 'Basic information',
        'sw-experience-studio.detail.elementSettings.mapping.groups.customFields': 'Custom Fields',
        'sw-experience-studio.detail.elementSettings.mapping.sources.rootEntity': 'Root entity',
        'sw-experience-studio.detail.elementSettings.mapping.sources.storefrontContext': 'Storefront context',
        'sw-experience-studio.createWizard.layoutTypes.product': 'Product',
    };

    const i18n = {
        $te: (key: string): boolean => Object.prototype.hasOwnProperty.call(translations, key),
        $t: (key: string): string => translations[key] ?? key,
        getCandidateTranslation: (): string => '',
    };

    it('renders a snippet label, falling back to the key when no snippet is shipped', () => {
        expect(methods.getCandidateLabel.call(i18n, candidate())).toBe('Category name');
        expect(methods.getCandidateLabel.call(i18n, candidate({ label: 'app.custom.label' }))).toBe('app.custom.label');
    });

    it('omits the description rather than showing a raw snippet key', () => {
        expect(methods.getCandidateDescription.call(i18n, candidate())).toBe('The name of the category the page shows.');
        expect(methods.getCandidateDescription.call(i18n, candidate({ description: 'app.custom.description' }))).toBe('');
    });

    it('prefers labels and descriptions supplied by dynamic candidate configuration', () => {
        const configuredCandidate = candidate({
            labelTranslations: { 'en-GB': 'Material' },
            descriptionTranslations: { 'en-GB': 'The product material' },
        });

        expect(
            methods.getCandidateLabel.call(
                {
                    ...i18n,
                    getCandidateTranslation: (values: Record<string, string>) => values['en-GB'],
                },
                configuredCandidate,
            ),
        ).toBe('Material');
        expect(
            methods.getCandidateDescription.call(
                {
                    ...i18n,
                    getCandidateTranslation: (values: Record<string, string>) => values['en-GB'],
                },
                configuredCandidate,
            ),
        ).toBe('The product material');
    });

    it('falls back to the raw group name when the group has no snippet', () => {
        expect(methods.getGroupLabel.call(i18n, 'basic')).toBe('Basic information');
        expect(methods.getGroupLabel.call(i18n, 'customFields')).toBe('Custom Fields');
        expect(methods.getGroupLabel.call(i18n, 'custom')).toBe('custom');
    });

    it('matches the search term against both the label and the path', () => {
        const candidates = [
            candidate({ path: 'category.name' }),
            candidate({
                path: 'category.metaTitle',
                label: 'sw-experience-studio.mapping.category.metaTitle.label',
                group: 'seo',
            }),
        ];

        const matchingByLabel = computed.matchingCandidates.call({
            ...i18n,
            candidates,
            searchTerm: 'category name',
            getCandidateLabel: methods.getCandidateLabel,
            getSourceLabel: methods.getSourceLabel,
        }) as ContentSystemMappingCandidate[];

        const matchingByPath = computed.matchingCandidates.call({
            ...i18n,
            candidates,
            searchTerm: 'metatitle',
            getCandidateLabel: methods.getCandidateLabel,
            getSourceLabel: methods.getSourceLabel,
        }) as ContentSystemMappingCandidate[];

        expect(matchingByLabel.map((entry) => entry.path)).toEqual(['category.name']);
        expect(matchingByPath.map((entry) => entry.path)).toEqual(['category.metaTitle']);
    });

    it('groups candidates by typed source and puts the root entity first', () => {
        const rootCandidate = candidate({ path: 'product.name', source: { type: 'root', id: 'product', path: 'name' } });
        const contextCandidate = candidate({
            path: 'context:storefront.currency.isoCode',
            source: { type: 'context', id: 'storefront', path: 'currency.isoCode' },
        });
        const sourceGroups = computed.sourceGroups.call({
            matchingCandidates: [contextCandidate, rootCandidate],
            getSourceKey: methods.getSourceKey,
        }) as Array<{ key: string; candidates: ContentSystemMappingCandidate[] }>;

        expect(sourceGroups.map((group) => group.key)).toEqual(['root:product', 'context:storefront']);
        expect(sourceGroups[0].candidates).toEqual([rootCandidate]);
        expect(sourceGroups[1].candidates).toEqual([contextCandidate]);
    });

    it('keeps the source containing the current mapping active', () => {
        const rootGroup = { key: 'root:product', type: 'root', id: 'product', candidates: [candidate()] };
        const contextCandidate = candidate({
            path: 'context:storefront.currency.isoCode',
            source: { type: 'context', id: 'storefront', path: 'currency.isoCode' },
        });
        const contextGroup = { key: 'context:storefront', type: 'context', id: 'storefront', candidates: [contextCandidate] };

        const active = computed.activeSourceGroup.call({
            sourceGroups: [rootGroup, contextGroup],
            selectedSourceKey: null,
            currentPath: contextCandidate.path,
        });

        expect(active).toBe(contextGroup);
    });

    it('labels root and Storefront context sources for the left navigation', () => {
        expect(methods.getSourceLabel.call(i18n, 'root', 'product')).toBe('Root entity · Product');
        expect(methods.getSourceLabel.call(i18n, 'context', 'storefront')).toBe('Storefront context');
    });

    it('returns every candidate for an empty search term', () => {
        const candidates = [
            candidate({ path: 'category.name' }),
            candidate({ path: 'category.description' }),
        ];

        const matching = computed.matchingCandidates.call({
            ...i18n,
            candidates,
            searchTerm: '   ',
            getCandidateLabel: methods.getCandidateLabel,
            getSourceLabel: methods.getSourceLabel,
        }) as ContentSystemMappingCandidate[];

        expect(matching).toHaveLength(2);
    });

    it('matches custom fields by their configured label', () => {
        const configuredCandidate = candidate({
            path: 'product.customFields.material',
            label: 'material',
            labelTranslations: { 'en-GB': 'Material' },
            group: 'customFields',
        });

        const matching = computed.matchingCandidates.call({
            ...i18n,
            candidates: [configuredCandidate],
            searchTerm: 'material',
            getCandidateLabel: methods.getCandidateLabel,
            getSourceLabel: methods.getSourceLabel,
        }) as ContentSystemMappingCandidate[];

        expect(matching).toEqual([configuredCandidate]);
    });

    it('distinguishes an empty catalogue from an empty search result', () => {
        const noCandidates = computed.emptyStateDescription.call({
            ...i18n,
            hasCandidates: false,
        });
        const noResults = computed.emptyStateDescription.call({
            ...i18n,
            hasCandidates: true,
        });

        expect(noCandidates).toBe('sw-experience-studio.detail.elementSettings.mapping.noCandidates');
        expect(noResults).toBe('sw-experience-studio.detail.elementSettings.mapping.noSearchResults');
    });

    it('emits the selected candidate so the caller can store its context type', () => {
        const emitted: unknown[] = [];
        const selected = candidate();

        methods.onSelectCandidate.call(
            {
                $emit: (event: string, payload: unknown) =>
                    emitted.push([
                        event,
                        payload,
                    ]),
            },
            selected,
        );

        expect(emitted).toEqual([
            [
                'select',
                selected,
            ],
        ]);
    });
});
