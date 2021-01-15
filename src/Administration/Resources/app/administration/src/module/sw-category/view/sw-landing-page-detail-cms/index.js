import template from './sw-landing-page-detail-cms.html.twig';
import './sw-landing-page-detail-cms.scss';

const { Component } = Shopware;

Component.register('sw-landing-page-detail-cms', {
    template,

    computed: {
        cmsPage() {
            return Shopware.State.get('cmsPageState').currentPage;
        }
    }
});
