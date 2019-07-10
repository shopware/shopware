[titleEn]: <>(Storefront Thumbnail helper)

Iterating over the thumbnails of a media object was exhausting and produced a lot of boilerplate code? This ends now!
We added a new thumbnail helper which automatically creates a `picture` element including the right `source` tags.

The helper accepts a string which will act as the `class` attribute of the `picture` element. The second parameter is a
 configuration object which supports the following properties:

* `thumbnails` - MediaThumbnailCollection
* `default` - URL to the default image if no thumbnail matches the screen real estate of the user
* `alt` - Text for the "alt" attribute
* `attributes` - Additional attributes for the "picture" element

### Example

```
{% sw_thumbnails "my-image-class" with {
    thumbnails: page.product.media.first.media.thumbnails,
    default: page.product.media.first.media.thumbnails.last.url,
    attributes: {
        'data-plugin-slider': true
    },
    alt: page.product.translated.name
} %}
```