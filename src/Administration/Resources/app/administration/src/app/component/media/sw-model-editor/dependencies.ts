/**
 * @sw-package innovation
 *
 * Everything sw-model-editor needs from outside its mini-module.
 */
/** @private */
export { DIVEMath, ModelComponent } from '@shopware-ag/dive';
/** @private */
export type { DIVENode } from '@shopware-ag/dive';
/** @private */
export { AssetExporter } from '@shopware-ag/dive/assetexporter';
/** @private */
export { QuickView } from '@shopware-ag/dive/quickview';
/** @private */
export { Toolbox } from '@shopware-ag/dive/toolbox';
/** @private */
export { Euler, Object3D } from 'three';
/** @private */
export type { Vector3 } from 'three';
/** @private */
export { defineComponent as wrapComponentConfig, markRaw } from 'vue';
/** @private */
export type { Raw } from 'vue';
/** @private */
export type { default as Repository } from 'src/core/data/repository.data';
/** @private */
export type { default as MediaService } from 'src/core/service/api/media.api.service';
/** @private */
export { default as EventBus } from 'src/core/service/utils/eventBus.utils';
/** @private */
export { mediaService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const getApiContext = () => Shopware.Context.api;
