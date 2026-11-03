/**
 * @sw-package framework
 *
 * Everything sw-chart needs from outside its mini-module.
 */
import { sessionStoreId } from 'src/app/store/session.store';

/** @private */
export { default as ar } from 'apexcharts/dist/locales/ar.json';
/** @private */
export { default as ca } from 'apexcharts/dist/locales/ca.json';
/** @private */
export { default as cs } from 'apexcharts/dist/locales/cs.json';
/** @private */
export { default as de } from 'apexcharts/dist/locales/de.json';
/** @private */
export { default as el } from 'apexcharts/dist/locales/el.json';
/** @private */
export { default as en } from 'apexcharts/dist/locales/en.json';
/** @private */
export { default as es } from 'apexcharts/dist/locales/es.json';
/** @private */
export { default as fa } from 'apexcharts/dist/locales/fa.json';
/** @private */
export { default as fi } from 'apexcharts/dist/locales/fi.json';
/** @private */
export { default as fr } from 'apexcharts/dist/locales/fr.json';
/** @private */
export { default as he } from 'apexcharts/dist/locales/he.json';
/** @private */
export { default as hi } from 'apexcharts/dist/locales/hi.json';
/** @private */
export { default as hr } from 'apexcharts/dist/locales/hr.json';
/** @private */
export { default as hu } from 'apexcharts/dist/locales/hu.json';
/** @private */
export { default as hy } from 'apexcharts/dist/locales/hy.json';
/** @private */
export { default as id } from 'apexcharts/dist/locales/id.json';
/** @private */
export { default as it } from 'apexcharts/dist/locales/it.json';
/** @private */
export { default as ja } from 'apexcharts/dist/locales/ja.json';
/** @private */
export { default as ko } from 'apexcharts/dist/locales/ko.json';
/** @private */
export { default as lt } from 'apexcharts/dist/locales/lt.json';
/** @private */
export { default as nb } from 'apexcharts/dist/locales/nb.json';
/** @private */
export { default as nl } from 'apexcharts/dist/locales/nl.json';
/** @private */
export { default as pl } from 'apexcharts/dist/locales/pl.json';
/** @private */
export { default as ptBr } from 'apexcharts/dist/locales/pt-br.json';
/** @private */
export { default as pt } from 'apexcharts/dist/locales/pt.json';
/** @private */
export { default as rs } from 'apexcharts/dist/locales/rs.json';
/** @private */
export { default as ru } from 'apexcharts/dist/locales/ru.json';
/** @private */
export { default as sk } from 'apexcharts/dist/locales/sk.json';
/** @private */
export { default as sl } from 'apexcharts/dist/locales/sl.json';
/** @private */
export { default as sq } from 'apexcharts/dist/locales/sq.json';
/** @private */
export { default as tr } from 'apexcharts/dist/locales/tr.json';
/** @private */
export { default as ua } from 'apexcharts/dist/locales/ua.json';
/** @private */
export { default as zhCn } from 'apexcharts/dist/locales/zh-cn.json';
/** @private */
export { default as VueApexCharts } from 'vue3-apexcharts';
/** @private */
export { feature as featureService } from 'src/app/service-names';

/** @private */
export const useSessionStore = () => Shopware.Store.get(sessionStoreId);

/** @private */
export const dateWithUserTimezone = (...args: Parameters<typeof Shopware.Utils.format.dateWithUserTimezone>) =>
    Shopware.Utils.format.dateWithUserTimezone(...args);

/** @private */
export const deepCopyObject = (...args: Parameters<typeof Shopware.Utils.object.deepCopyObject>) =>
    Shopware.Utils.object.deepCopyObject(...args);

/** @private */
export const merge = (...args: Parameters<typeof Shopware.Utils.object.merge>) => Shopware.Utils.object.merge(...args);

/** @private */
export const warn = (...args: Parameters<typeof Shopware.Utils.debug.warn>) => Shopware.Utils.debug.warn(...args);
