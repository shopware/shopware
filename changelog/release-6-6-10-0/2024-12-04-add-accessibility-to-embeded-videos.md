---
title: add accessibility to embeded videos
issue: NEXT-39088
---
# Administration
* Added `title` attribute to the YouTube and Vimeo components for improved accessibility in `src/module/sw-cms/elements/vimeo-video/index.ts` and `src/module/sw-cms/elements/youtube-video/component/index.js`
___
# Storefront
* Changed `element/cms-element-vimeo-video.html.twig` to enable subtitles by default
* Changed `element/cms-element-youtube-video.html.twig` to enable subtitles by default and added auto-translation to the current website language
* Added link to "Watch on Vimeo" within `element/cms-element-vimeo-video.html.twig`, providing direct access to the video on the Vimeo platform
