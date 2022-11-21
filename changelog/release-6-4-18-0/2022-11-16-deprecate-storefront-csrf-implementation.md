---
title: Deprecate Storefront CSRF implementation
issue: NEXT-23819
---
# Storefront
* Deprecated CSRF implementation in favor of SameSite cookies
    * Deprecated JavaScript plugin `FormCsrfHandler` and all usages of `data-form-csrf-handler`
    * Deprecated CSRF implementation in method `_onChange` in `FormAutoSubmitPlugin`
    * Deprecated CSRF implementation in `HttpClient`
        * Deprecated constructor fields `_csrfEnabled`, `_csrfMode`, `_generateUrl`
        * Deprecated method `fetchCsrfToken`
        * Deprecated parameter `csrfProtected` in method `post`
    * Deprecated option `csrfToken` in `AddressEditorPlugin`
    * Deprecated `_csrf_token` for all `HttpClient` requests in `WishlistPersistStoragePlugin`
    * Deprecated JavaScript property `window.csrf`
    * Deprecated JavaScript property `window.storeApiProxyToken`
    * Deprecated route `frontend.csrf.generateToken` in `window.router`
    * Deprecated block `layout_head_javascript_csrf` in `Resources/views/storefront/layout/meta.html.twig`
    * Deprecated block `page_account_payment_form_csrf` in `Resources/views/storefront/page/account/payment/index.html.twig`
    * Deprecated block `layout_header_actions_currency_widget_form_csrf` in `Resources/views/storefront/layout/header/actions/currency-widget.html.twig`
    * Deprecated block `layout_header_actions_language_widget_form_csrf` in `Resources/views/storefront/layout/header/actions/language-widget.html.twig`
    * Deprecated block `component_account_login_form_csrf` in `Resources/views/storefront/component/account/login.html.twig`
    * Deprecated block `component_account_register_form_csrf` in `Resources/views/storefront/component/account/register.html.twig`
    * Deprecated block `component_address_address_editor_modal_create_address_form_csrf` in `Resources/views/storefront/component/address/address-editor-modal-create-address.html.twig`
    * Deprecated block `page_checkout_change_payment_form_csrf` in `Resources/views/storefront/component/payment/payment-form.html.twig`
    * Deprecated block `component_review_form_csrf` in `Resources/views/storefront/component/review/review-form.html.twig`
    * Deprecated block `component_review_filter_csrf` in `Resources/views/storefront/component/review/review-widget.html.twig`
    * Deprecated block `component_review_list_action_language_csrf` in `Resources/views/storefront/component/review/review.html.twig`
    * Deprecated block `component_review_list_action_sortby_form_csrf` in `Resources/views/storefront/component/review/review.html.twig`
    * Deprecated block `component_review_list_paging_csrf` in `Resources/views/storefront/component/review/review.html.twig`
    * Deprecated block `page_account_address_actions_set_default_shipping_csrf` in `Resources/views/storefront/page/account/addressbook/address-actions.html.twig`
    * Deprecated block `page_account_address_actions_set_default_billing_csrf` in `Resources/views/storefront/page/account/addressbook/address-actions.html.twig`
    * Deprecated block `page_account_address_actions_delete_csrf` in `Resources/views/storefront/page/account/addressbook/address-actions.html.twig`
    * Deprecated block `page_account_overview_newsletter_content_form_csrf` in `Resources/views/storefront/page/account/newsletter.html.twig`
    * Deprecated block `component_address_address_editor_modal_list_address_action_billing_form_csrf` in `Resources/views/storefront/component/address/address-editor-modal-list.html.twig`
    * Deprecated block `component_address_address_editor_modal_list_address_action_shipping_form_csrf` in `Resources/views/storefront/component/address/address-editor-modal-list.html.twig`
    * Deprecated block `page_checkout_aside_cancel_order_modal_footer_form_csrf` in `Resources/views/storefront/page/account/order/cancel-order-modal.html.twig`
    * Deprecated block `page_account_profile_personal_form_csrf` in `Resources/views/storefront/page/account/profile/index.html.twig`
    * Deprecated block `page_account_profile_mail_form_csrf` in `Resources/views/storefront/page/account/profile/index.html.twig`
    * Deprecated block `page_account_delete_account_confirm_form_csrf` in `Resources/views/storefront/page/account/profile/index.html.twig`
    * Deprecated block `page_account_profile_password_form_csrf` in `Resources/views/storefront/page/account/profile/index.html.twig`
    * Deprecated block `page_account_address_form_create_csrf` in `Resources/views/storefront/page/account/addressbook/create.html.twig`
    * Deprecated block `buy_widget_buy_form_inner_csrf` in `Resources/views/storefront/component/buy-widget/buy-widget-form.html.twig`
    * Deprecated block `buy_widget_configurator_csrf` in `Resources/views/storefront/component/buy-widget/configurator.html.twig`
    * Deprecated block `page_checkout_confirm_shipping_form_csrf` in `Resources/views/storefront/component/checkout/offcanvas-cart-summary.html.twig`
    * Deprecated block `page_checkout_aside_actions_csrf` in `Resources/views/storefront/page/account/order/index.html.twig`
    * Deprecated block `component_offcanvas_cart_actions_promotion_form_csrf` in `Resources/views/storefront/component/checkout/offcanvas-cart.html.twig`
    * Deprecated block `component_offcanvas_product_quantity_form_csrf` in `Resources/views/storefront/component/checkout/offcanvas-item.html.twig`
    * Deprecated block `component_offcanvas_product_remove_form_csrf` in `Resources/views/storefront/component/checkout/offcanvas-item.html.twig`
    * Deprecated block `component_line_item_quantity_csrf` in `Resources/views/storefront/component/line-item/element/quantity.html.twig`
    * Deprecated block `component_line_item_remove_csrf` in `Resources/views/storefront/component/line-item/element/remove.html.twig`
    * Deprecated block `component_product_box_action_buy_csrf` in `Resources/views/storefront/component/product/card/action.html.twig`
    * Deprecated block `page_checkout_cart_add_product_csrf` in `Resources/views/storefront/page/checkout/cart/index.html.twig`
    * Deprecated block `page_checkout_cart_shipping_costs_csrf` in `Resources/views/storefront/page/checkout/cart/index.html.twig`
    * Deprecated block `page_checkout_cart_add_promotion_csrf` in `Resources/views/storefront/page/checkout/cart/index.html.twig`
    * Deprecated block `component_product_box_wishlist_remove_csrf` in `Resources/views/storefront/component/product/card/box-wishlist.html.twig`
    * Deprecated block `page_checkout_change_shipping_form_csrf` in `Resources/views/storefront/component/shipping/shipping-form.html.twig`
    * Deprecated block `page_checkout_aside_actions_csrf` in `Resources/views/storefront/page/checkout/confirm/index.html.twig`
    * Deprecated block `page_product_detail_buy_form_inner_csrf` in `Resources/views/storefront/page/product-detail/buy-widget-form.html.twig`
    * Deprecated block `page_product_detail_configurator_csrf` in `Resources/views/storefront/page/product-detail/configurator.html.twig`
    * Deprecated block `page_product_detail_review_form_csrf` in `Resources/views/storefront/page/product-detail/review/review-form.html.twig`
    * Deprecated block `page_product_detail_review_filter_csrf` in `Resources/views/storefront/page/product-detail/review/review-widget.html.twig`
    * Deprecated block `page_product_detail_review_list_action_language_csrf` in `Resources/views/storefront/page/product-detail/review/review.html.twig`
    * Deprecated block `page_product_detail_review_list_action_sortby_form_csrf` in `Resources/views/storefront/page/product-detail/review/review.html.twig`
    * Deprecated block `page_product_detail_review_list_paging_csrf` in `Resources/views/storefront/page/product-detail/review/review.html.twig`
    * Deprecated block `page_checkout_aside_cancel_order_modal_footer_form_csrf` in `Resources/views/storefront/page/account/order-history/cancel-order-modal.html.twig`
    * Deprecated block `cms_form_contact_csrf` in `Resources/views/storefront/element/cms-element-form/form-types/contact-form.html.twig`
    * Deprecated block `cms_form_newsletter_csrf` in `Resources/views/storefront/element/cms-element-form/form-types/newsletter-form.html.twig`
    * Deprecated block `page_account_address_form_edit_csrf` in `Resources/views/storefront/page/account/addressbook/edit.html.twig`
    * Deprecated block `page_account_orders_paging_csrf` in `Resources/views/storefront/page/account/order-history/index.html.twig`
    * Deprecated block `page_account_order_item_context_menu_reorder_form_csrf` in `Resources/views/storefront/page/account/order-history/order-item.html.twig`
    * Deprecated `sw_csrf` usage in `Resources/views/storefront/page/account/profile/reset-password.html.twig`
    * Deprecated `sw_csrf` usage in `Resources/views/storefront/page/account/profile/recover-password.html.twig`
    * Deprecated `sw_csrf` usage in `Resources/views/storefront/page/account/guest-auth.html.twig`
    * Deprecated option `basicCaptchaOptions.preCheckRoute.token` in `Resources/views/storefront/component/captcha/basicCaptcha.html.twig`
    * Deprecated options `addToWishlistOptions.router.add.token` and `addToWishlistOptions.remove.add.token` in `Resources/views/storefront/component/product/card/wishlist.html.twig`
    * Deprecated options `wishlistStorageOptions.tokenMergePath` and `wishlistStorageOptions.tokenPageletPath` in `Resources/views/storefront/layout/header/actions/wishlist-widget.html.twig`
    * Deprecated option `addressEditorOptions.csrfToken` in `Resources/views/storefront/page/account/address.html.twig`
    * Deprecated option `addressEditorOptions.csrfToken` in `Resources/views/storefront/page/checkout/confirm/confirm-address.html.twig`
    * Deprecated option `guestWishlistPageOptions.pageletRouter.token` in `Resources/views/storefront/page/wishlist/index.html.twig`
    * Deprecated `\Shopware\Storefront\Framework\Twig\Extension\CsrfFunctionExtension`
        * Deprecated twig function `sw_csrf`
