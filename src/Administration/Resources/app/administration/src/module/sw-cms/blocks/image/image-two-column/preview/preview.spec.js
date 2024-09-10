/**
 * @package buyers-experience
 */
import { runGenericCmsTest } from 'src/module/sw-cms/test-utils';
import component from './index';

describe('src/module/sw-cms/blocks/image-two-column/preview', () => {
    runGenericCmsTest(component);
});
