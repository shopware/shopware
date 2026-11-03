/**
 * @sw-package fundamentals@after-sales
 *
 * Everything sw-import-export-new-profile-wizard needs from outside its mini-module.
 */
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export {
    feature as featureService,
    importExportProfileMapping as importExportProfileMappingService,
    repositoryFactory as repositoryFactoryService,
} from 'src/app/service-names';
