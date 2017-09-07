import VueAdapter from 'src/app/adapter/view/vue.adapter';
import ViewFactory from 'src/core/factory/view.factory';

export default function initializeView(app, configuration, done) {
    const vueAdapter = VueAdapter(configuration.context);

    configuration.view = ViewFactory(vueAdapter);
    configuration.view.initComponents();

    done(configuration);
}
