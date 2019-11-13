import template from './sw-category-detail-cms.html.twig';
import './sw-category-detail-cms.scss';

const { Component } = Shopware;

Component.register('sw-category-detail-cms', {
    template,

    computed: {
        cmsPage() {
            return Shopware.State.get('cmsPageState').currentPage;
        }
    }
});
