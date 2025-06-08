---
title: Improve rating widget accessibility and add alt texts
issue: NEXT-35896
---
# Storefront
* Added new optional include parameter `altText` to `Resources/views/storefront/component/review/rating.html.twig` and added default alternative text for screen readers.
___
# Upgrade Information
## Rating widget alternative text for improved accessibility
The twig template that renders the rating stars (`Resources/views/storefront/component/review/rating.html.twig`) now supports an alternative text for screen readers:
```diff
{% sw_include '@Storefront/storefront/component/review/rating.html.twig' with {
    points: points,
+    altText: 'translation.key.example'|trans({ '%points%': points, '%maxPoints%': maxPoints })|sw_sanitize,
} %}
```

Instead of reading the rating star icons as "graphic", the screen reader will read the alternative text, e.g. `Average rating of 3 out of 5 stars`.
By default, the `rating.html.twig` template will always use the alternative text with translation `detail.reviewAvgRatingAltText`, unless it is overwritten by the `altText` include parameter.

The `rating.html.twig` template will now render the alternative text as shown below:
```diff
<div class="product-review-rating">               
    <!-- Review star SVGs are now hidden for the screen reader, alt text is read instead. -->
    <div class="product-review-point" aria-hidden="true"></div>
    <div class="product-review-point" aria-hidden="true"></div>
    <div class="product-review-point" aria-hidden="true"></div>
    <div class="product-review-point" aria-hidden="true"></div>
    <div class="product-review-point" aria-hidden="true"></div>
+    <p class="product-review-rating-alt-text visually-hidden">
+        Average rating of 4 out of 5 stars
+    </p>
</div>
```