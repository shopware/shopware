import objectFitImages from 'object-fit-images';
import '@babel/polyfill';
import 'form-association-polyfill/dist/form-association-polyfill-register-with-shims';
import 'mdn-polyfills/NodeList.prototype.forEach';
import 'mdn-polyfills/CustomEvent';
import 'mdn-polyfills/MouseEvent';
import 'picturefill';
import 'picturefill/dist/plugins/mutation/pf.mutation';
import ElementClosestPolyfill from 'element-closest';
import 'formdata-polyfill';
import 'object-fit-polyfill';
import 'intersection-observer';
import 'report-validity';

ElementClosestPolyfill(window);
objectFitImages();
