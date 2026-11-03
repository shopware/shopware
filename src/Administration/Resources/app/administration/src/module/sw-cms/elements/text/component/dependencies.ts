/**
 * @sw-package discovery
 *
 * Everything sw-cms-el-text needs from outside its mini-module.
 */
import { cmsPageStoreId } from '../../../store/cms-page.store';

/** @private */
export { feature as featureService } from 'src/app/service-names';
/** @private */
export { default as SwTextEditorToolbarButtonCmsDataMappingButton } from '../../../../../app/component/meteor-wrapper/mt-text-editor/sw-text-editor-toolbar-button-cms-data-mapping';
/** @private */
export { default as cmsElementMixin } from '../../../mixin/sw-cms-element.mixin';

/** @private */
export const useCmsPageStore = () => Shopware.Store.get(cmsPageStoreId);
