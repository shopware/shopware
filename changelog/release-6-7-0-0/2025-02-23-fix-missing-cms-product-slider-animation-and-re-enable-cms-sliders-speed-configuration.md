---
title: Fix missing CMS product slider animation and re-enable CMS sliders speed configuration
author: Elias Lackner
author_email: lackner.elias@gmail.com
author_github: @lacknere
---
# Core
* Added `Shopware\Core\Migration\V6_7\Migration1740321707SetAutoplayTimeoutAndSpeedSettingsForProductSlider` to set the default autoplay timeout and speed settings for product slider CMS elements.
___
# Administration
* Deprecated block `sw_cms_element_image_gallery_config_settings_display_mode` in `sw-cms-el-config-image-slider.html.twig`. Use `sw_cms_element_image_slider_config_settings_display_mode` instead.
* Deprecated block `sw_cms_element_image_gallery_config_settings_display_mode_select` in `sw-cms-el-config-image-slider.html.twig`. Use `sw_cms_element_image_slider_config_settings_display_mode_select` instead.
* Deprecated block `sw_cms_element_image_gallery_config_settings_min_height` in `sw-cms-el-config-image-slider.html.twig`. Use `sw_cms_element_image_slider_config_settings_min_height` instead.
* Deprecated block `sw_cms_element_image_gallery_config_settings_vertical_align` in `sw-cms-el-config-image-slider.html.twig`. Use `sw_cms_element_image_slider_config_settings_vertical_align` instead.
* Added new blocks to `sw-cms-el-config-image-slider.html.twig`:
    - `sw_cms_element_image_slider_config_settings_speed`
    - `sw_cms_element_image_slider_config_settings_auto_slide`
    - `sw_cms_element_image_slider_config_settings_autoplay_timeout`
* Removed `disabled` property from the speed configuration fields and changed positions with the rotate/auto-slide switches in `sw-cms-el-config-image-slider.html.twig` and `sw-cms-el-config-product-slider.html.twig`.
