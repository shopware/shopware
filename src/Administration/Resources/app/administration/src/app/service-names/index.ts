/**
 * @sw-package framework
 *
 * The names services are registered and injected under.
 * Import a name from here instead of writing it, so a renamed service fails the build, not the page.
 * This file is the public API, also for extensions. The files in `./src` are private.
 */
/**
 * @public
 * @shopware-kind framework
 */
export {
    acl,
    cacheApiService,
    cacheService,
    captchaService,
    customFieldDataProviderService,
    customSnippetApiService,
    documentV2ApiService,
    documentV2Service,
    excludedSearchTermService,
    feature,
    filterFactory,
    filterService,
    importExport,
    liveSearchService,
    messageStatsService,
    numberRangeService,
    productIndexService,
    repositoryFactory,
    ruleConditionDataProviderService,
    ruleConditionsConfigApiService,
    seoUrlTemplateService,
    serviceRegistryClient,
    shopwareServicesService,
    snippetService,
    snippetSetService,
    syncService,
    systemConfigApiService,
    tagApiService,
    translationService,
    updateService,
    userConfigService,
    userService,
} from './src/service-names';
