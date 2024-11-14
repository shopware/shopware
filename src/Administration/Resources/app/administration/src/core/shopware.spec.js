/**
 * @package admin
 */

const {
    Module,
    Component,
    Template,
    Mixin,
    Filter,
    Directive,
    Locale,
    Shortcut,
    Utils,
    ApiService,
    EntityDefinition,
    WorkerNotification,
    Data,
    Classes,
    Helper,
    Vue,
} = Shopware;

describe('core/shopware', () => {
    it('should contain the necessary methods for the module factory', async () => {
        expect(Module).toHaveProperty('register');
    });

    it('should contain the necessary methods for the component factory', async () => {
        expect(Component).toHaveProperty('register');
        expect(Component).toHaveProperty('extend');
        expect(Component).toHaveProperty('override');
        expect(Component).toHaveProperty('build');
        expect(Component).toHaveProperty('getTemplate');
    });

    it('should contain the necessary methods for the template factory', async () => {
        expect(Template).toHaveProperty('register');
        expect(Template).toHaveProperty('extend');
        expect(Template).toHaveProperty('override');
        expect(Template).toHaveProperty('getRenderedTemplate');
    });

    it('should contain the necessary methods for the mixin factory', async () => {
        expect(Mixin).toHaveProperty('register');
        expect(Mixin).toHaveProperty('getByName');
    });

    it('should contain the necessary methods for the filter factory', async () => {
        expect(Filter).toHaveProperty('register');
        expect(Filter).toHaveProperty('getByName');
    });

    it('should contain the necessary methods for the directive factory', async () => {
        expect(Directive).toHaveProperty('register');
        expect(Directive).toHaveProperty('getByName');
    });

    it('should contain the necessary methods for the locale factory', async () => {
        expect(Locale).toHaveProperty('register');
        expect(Locale).toHaveProperty('extend');
        expect(Locale).toHaveProperty('getByName');
    });

    it('should contain the necessary methods for the shortcut factory', async () => {
        expect(Shortcut).toHaveProperty('register');
        expect(Shortcut).toHaveProperty('getShortcutRegistry');
        expect(Shortcut).toHaveProperty('getPathByCombination');
    });

    it('should contain the necessary methods for the utils', async () => {
        expect(Utils).toHaveProperty('throttle');
        expect(Utils).toHaveProperty('debounce');
        expect(Utils).toHaveProperty('get');
        expect(Utils).toHaveProperty('object');
        expect(Utils).toHaveProperty('debug');
        expect(Utils).toHaveProperty('format');
        expect(Utils).toHaveProperty('dom');
        expect(Utils).toHaveProperty('string');
        expect(Utils).toHaveProperty('types');
        expect(Utils).toHaveProperty('fileReader');
        expect(Utils).toHaveProperty('sort');
        expect(Utils).toHaveProperty('array');
    });

    it('should contain the necessary methods for the ApiService', async () => {
        expect(ApiService).toHaveProperty('register');
        expect(ApiService).toHaveProperty('getByName');
        expect(ApiService).toHaveProperty('getRegistry');
        expect(ApiService).toHaveProperty('getServices');
        expect(ApiService).toHaveProperty('has');
    });

    it('should contain the necessary methods for the EntityDefinition', async () => {
        expect(EntityDefinition).toHaveProperty('getScalarTypes');
        expect(EntityDefinition).toHaveProperty('getJsonTypes');
        expect(EntityDefinition).toHaveProperty('getDefinitionRegistry');
        expect(EntityDefinition).toHaveProperty('get');
        expect(EntityDefinition).toHaveProperty('add');
        expect(EntityDefinition).toHaveProperty('remove');
        expect(EntityDefinition).toHaveProperty('getTranslatedFields');
        expect(EntityDefinition).toHaveProperty('getAssociationFields');
        expect(EntityDefinition).toHaveProperty('getRequiredFields');
    });

    it('should contain the necessary methods for the WorkerNotification', async () => {
        expect(WorkerNotification).toHaveProperty('register');
        expect(WorkerNotification).toHaveProperty('getRegistry');
        expect(WorkerNotification).toHaveProperty('override');
        expect(WorkerNotification).toHaveProperty('remove');
        expect(WorkerNotification).toHaveProperty('initialize');
    });

    it('should contain the necessary methods for the Data', async () => {
        expect(Data).toHaveProperty('ChangesetGenerator');
        expect(Data).toHaveProperty('Criteria');
        expect(Data).toHaveProperty('Entity');
        expect(Data).toHaveProperty('EntityCollection');
        expect(Data).toHaveProperty('EntityDefinition');
        expect(Data).toHaveProperty('EntityFactory');
        expect(Data).toHaveProperty('EntityHydrator');
        expect(Data).toHaveProperty('Repository');
    });

    it('should contain the necessary methods for the Classes', async () => {
        expect(Classes).toHaveProperty('ShopwareError');
        expect(Classes).toHaveProperty('ApiService');
    });

    it('should contain the necessary methods for the Helper', async () => {
        expect(Helper).toHaveProperty('FlatTreeHelper');
        expect(Helper).toHaveProperty('MiddlewareHelper');
    });

    it('should contain the necessary methods for "Vue"', async () => {
        expect(Vue).toHaveProperty('ref');
        expect(Vue).toHaveProperty('computed');
        expect(Vue).toHaveProperty('reactive');
        expect(Vue).toHaveProperty('readonly');
        expect(Vue).toHaveProperty('watchEffect');
        expect(Vue).toHaveProperty('watchPostEffect');
        expect(Vue).toHaveProperty('watchSyncEffect');
        expect(Vue).toHaveProperty('watch');
        expect(Vue).toHaveProperty('isRef');
        expect(Vue).toHaveProperty('unref');
        expect(Vue).toHaveProperty('toRef');
        expect(Vue).toHaveProperty('toRefs');
        expect(Vue).toHaveProperty('toValue');
        expect(Vue).toHaveProperty('isProxy');
        expect(Vue).toHaveProperty('isReactive');
        expect(Vue).toHaveProperty('isReadonly');
        expect(Vue).toHaveProperty('shallowRef');
        expect(Vue).toHaveProperty('triggerRef');
        expect(Vue).toHaveProperty('customRef');
        expect(Vue).toHaveProperty('shallowReactive');
        expect(Vue).toHaveProperty('shallowReadonly');
        expect(Vue).toHaveProperty('toRaw');
        expect(Vue).toHaveProperty('markRaw');
        expect(Vue).toHaveProperty('effectScope');
        expect(Vue).toHaveProperty('getCurrentScope');
        expect(Vue).toHaveProperty('onScopeDispose');
        expect(Vue).toHaveProperty('onMounted');
        expect(Vue).toHaveProperty('onUpdated');
        expect(Vue).toHaveProperty('onUnmounted');
        expect(Vue).toHaveProperty('onBeforeMount');
        expect(Vue).toHaveProperty('onBeforeUpdate');
        expect(Vue).toHaveProperty('onBeforeUnmount');
        expect(Vue).toHaveProperty('onErrorCaptured');
        expect(Vue).toHaveProperty('onRenderTracked');
        expect(Vue).toHaveProperty('onRenderTriggered');
        expect(Vue).toHaveProperty('onActivated');
        expect(Vue).toHaveProperty('onDeactivated');
        expect(Vue).toHaveProperty('onServerPrefetch');
        expect(Vue).toHaveProperty('provide');
        expect(Vue).toHaveProperty('inject');
        expect(Vue).toHaveProperty('hasInjectionContext');
        expect(Vue).toHaveProperty('useAttrs');
        expect(Vue).toHaveProperty('useSlots');
        expect(Vue).toHaveProperty('useModel');
    });
});
