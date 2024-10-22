import './component';
import './config';
import './preview';

/**
 * @private
 * @package buyers-experience
 */
export interface ElementDataProp {
    name: string;
    label: string;
    component: string;
    previewComponent: string;
    configComponent: string;
    defaultConfig: {
        [key: string]: unknown;
    };
    appData: {
        baseUrl: string;
    };
}
