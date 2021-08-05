import './sw-overlay.scss';
import template from './sw-overlay.html.twig';

const { Component } = Shopware;

/**
 * @public
 * @description Renders an overlay layer for panels, input fields, buttons, etc.
 * @status ready
 * @example-type dynamic
 * @component-example
 * <sw-overlay></sw-overlay>
 */
Component.register('sw-overlay', {
    template,
});
