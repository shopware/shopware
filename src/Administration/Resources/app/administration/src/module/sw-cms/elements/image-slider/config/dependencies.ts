/**
 * @sw-package discovery
 *
 * Everything sw-cms-el-config-image-slider needs from outside its mini-module.
 */
/** @private */
export { default as Utils } from 'src/core/service/util.service';
/** @private */
export { feature as featureService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';
/** @private */
export { default as cmsElementMixin } from '../../../mixin/sw-cms-element.mixin';
