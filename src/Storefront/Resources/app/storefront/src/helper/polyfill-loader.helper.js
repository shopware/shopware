/**
 * @deprecated tag:v6.5.0 - Polyfill will be removed because IE11 support will be discontinued.
 * @see https://developer.mozilla.org/de/docs/Web/CSS/object-fit
 */
import objectFitImages from 'object-fit-images';

import '@babel/polyfill';

/**
 * @deprecated tag:v6.5.0 - Polyfill will be removed because IE11 support will be discontinued.
 * @see https://github.com/paulzi/form-association-polyfill#readme
 * @see https://caniuse.com/form-attribute
 */
import 'form-association-polyfill/dist/form-association-polyfill-register-with-shims';

/**
 * @deprecated tag:v6.5.0 - Polyfill will be removed because IE11 support will be discontinued.
 * @see https://developer.mozilla.org/en-US/docs/Web/API/NodeList/forEach#browser_compatibility
 */
import 'mdn-polyfills/NodeList.prototype.forEach';

/**
 * @deprecated tag:v6.5.0 - Polyfill will be removed because IE11 support will be discontinued.
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CustomEvent/CustomEvent#browser_compatibility
 */
import 'mdn-polyfills/CustomEvent';

/**
 * @deprecated tag:v6.5.0 - Polyfill will be removed because IE11 support will be discontinued.
 * @see https://developer.mozilla.org/en-US/docs/Web/API/MouseEvent/MouseEvent#browser_compatibility
 */
import 'mdn-polyfills/MouseEvent';

/**
 * @deprecated tag:v6.5.0 - Polyfill will be removed because IE11 support will be discontinued.
 * @see https://developer.mozilla.org/en-US/docs/Web/HTML/Element/picture#browser_compatibility
 */
import 'picturefill';

/**
 * @deprecated tag:v6.5.0 - Polyfill will be removed because IE11 support will be discontinued.
 * @see https://developer.mozilla.org/en-US/docs/Web/HTML/Element/picture#browser_compatibility
 */
import 'picturefill/dist/plugins/mutation/pf.mutation';

/**
 * @deprecated tag:v6.5.0 - Polyfill will be removed because IE11 support will be discontinued.
 * @see https://developer.mozilla.org/en-US/docs/Web/API/Element/closest#browser_compatibility
 */
import ElementClosestPolyfill from 'element-closest';

/**
 * @deprecated tag:v6.5.0 - Polyfill will be removed because IE11 support will be discontinued.
 * @see https://developer.mozilla.org/en-US/docs/Web/API/FormData#browser_compatibility
 */
import 'formdata-polyfill';

/**
 * @deprecated tag:v6.5.0 - Polyfill will be removed because IE11 support will be discontinued.
 * @see https://developer.mozilla.org/en-US/docs/Web/CSS/object-fit#browser_compatibility
 */
import 'object-fit-polyfill';

/**
 * @deprecated tag:v6.5.0 - Polyfill will be removed because IE11 support will be discontinued.
 * @see https://developer.mozilla.org/en-US/docs/Web/API/IntersectionObserver#browser_compatibility
 */
import 'intersection-observer';

/**
 * @deprecated tag:v6.5.0 - Polyfill will be removed because IE11 support will be discontinued.
 * @see https://developer.mozilla.org/en-US/docs/Web/API/HTMLFormElement/reportValidity
 */
import 'report-validity';

ElementClosestPolyfill(window);
objectFitImages();
