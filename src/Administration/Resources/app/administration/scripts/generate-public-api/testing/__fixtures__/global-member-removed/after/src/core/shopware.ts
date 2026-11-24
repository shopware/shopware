/**
 * @sw-package framework
 */
import ExampleFactory from 'src/core/factory/example.factory';
import utils from 'src/core/service/util.service';

class ShopwareClass {
    public Example = {
        register: ExampleFactory.register,
    };

    public Utils = utils;
}

const ShopwareInstance = new ShopwareClass();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export { ShopwareClass, ShopwareInstance };
