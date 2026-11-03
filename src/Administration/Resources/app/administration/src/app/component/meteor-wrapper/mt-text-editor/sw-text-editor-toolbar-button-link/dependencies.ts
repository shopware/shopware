/**
 * @sw-package framework
 *
 * Everything sw-text-editor-toolbar-button-link needs from outside its mini-module.
 */
/** @private */
export type { CustomButton } from '@shopware-ag/meteor-component-library/dist/esm/MtTextEditorToolbar';
/** @private */
export type { Editor } from '@tiptap/vue-3';
/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as EntityCollection } from 'src/core/data/entity-collection.data';
/** @private */
export { repositoryFactory as repositoryFactoryService } from 'src/app/service-names';
/** @private */
export type { default as CriteriaType } from '../../../../../core/data/criteria.data';
/** @private */
export type { default as EntityCollectionType } from '../../../../../core/data/entity-collection.data';
/** @private */
export type { default as RepositoryType } from '../../../../../core/data/repository.data';

/** @private */
export const getApiContext = () => Shopware.Context.api;
