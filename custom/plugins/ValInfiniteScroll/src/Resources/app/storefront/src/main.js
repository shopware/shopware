import InfiniteScrollPlugin from './plugin/infinite-scroll.plugin';

const PluginManager = window.PluginManager;

PluginManager.register('InfiniteScroll', InfiniteScrollPlugin, '[data-infinite-scroll]');
