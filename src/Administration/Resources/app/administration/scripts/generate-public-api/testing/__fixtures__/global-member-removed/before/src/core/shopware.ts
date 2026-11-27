/**
 * @sw-package framework
 */
import ExampleFactory from 'src/core/factory/example.factory';
import utils from 'src/core/service/util.service';
import ExampleHelper from 'src/core/helper/example.helper';

class ShopwareClass {
    public Example = {
        register: ExampleFactory.register,
        getByName: ExampleFactory.getByName,
        getRegistry: ExampleFactory.getRegistry,
    };

    public Utils = utils;

    public Helper = {
        ExampleHelper,
    };
}

const ShopwareInstance = new ShopwareClass();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export { ShopwareClass, ShopwareInstance };
