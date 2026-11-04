/**
 * @sw-package framework
 */
import * as SwInlineSnippetMixin from './index';

Shopware.Mixin.register('sw-inline-snippet', SwInlineSnippetMixin.default);
Shopware._private.publish('mixins', 'sw-inline-snippet', SwInlineSnippetMixin);
