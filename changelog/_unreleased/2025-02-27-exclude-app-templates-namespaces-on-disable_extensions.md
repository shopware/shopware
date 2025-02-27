---
title: Exclude app namespaces in BundleHierarchyBuilder when DISABLE_EXTENSIONS is set
issue: 6842
---
# Core
* Changed the `\Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\BundleHierarchyBuilder` to exclude apps namespaces when `DISABLE_EXTENSIONS` environment variable is set.
