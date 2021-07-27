---
title: Deprecation of case-insensitive annotation parsing
issue: NEXT-16397
---
# Core
- Changed AnnotationReader to be case-insensitive

___
# Upgrade Information

## Deprecated of case-insensitive annotation parsing

With Shopware 6.5.0.0 the annotation parsing will be case-sensitive. 
Make sure to check that all your annotation properties fit their respective name case. 
E.g.: In case of the `Route` annotation you can have a look into the name case of the constructor parameters of the `\Symfony\Component\Routing\Annotation\Route` class.

Before:

```
@Route("/", name="frontend.home.page", Options={"seo"="true"}, Methods={"GET"})
```

After:

```
@Route("/", name="frontend.home.page", options={"seo"="true"}, methods={"GET"})
```
