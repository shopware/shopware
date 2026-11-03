/**
 * @sw-package checkout
 *
 * Everything sw-customer-base-form needs from outside its mini-module.
 */
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { feature as featureService } from 'src/app/service-names';
/** @private */
export { mapPropertyErrors } from 'src/app/service/map-errors.service';
/** @private */
export { default as CUSTOMER } from '../../constant/sw-customer.constant';
/** @private */
export { default as errorConfig } from '../../error-config.json';
