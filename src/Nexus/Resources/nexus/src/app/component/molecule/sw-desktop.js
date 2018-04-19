import ComponentFactory from 'src/core/factory/component.factory';
import template from 'src/app/component/molecule/sw-desktop/sw-desktop.html.twig';

export default ComponentFactory.register('sw-desktop', {

    template,

    created() {
        console.log(this);
    }
});
