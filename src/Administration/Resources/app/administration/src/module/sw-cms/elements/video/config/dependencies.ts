/**
 * @sw-package discovery
 *
 * Everything sw-cms-el-config-video needs from outside its mini-module.
 */
/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export type { default as RepositoryType } from 'src/core/data/repository.data';
/** @private */
export { repositoryFactory as repositoryFactoryService } from 'src/app/service-names';
/** @private */
export type { RuntimeSlot } from 'src/module/sw-cms/service/cms.service';
/** @private */
export { default as cmsElementMixin } from '../../../mixin/sw-cms-element.mixin';
