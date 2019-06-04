Documentation
=============

A collection of documentation to build the Shopware 6 documentation. 

This repository is considered **read-only**. Please send pull requests
to our [main Shopware\Core repository](https://github.com/shopware/platform).

File structure of the documentation
----------

- The whole documentation for the Shopware platform is stored in the `Resources/current` directory
- An guide's name has to follow this pattern: `000-name-of-the-guide.md` or `<next available three-digit number> - <name of the guide>.md`
- Each guide has to implement the meta tag `titleEn`!
- An category's name has to follow this pattern: `000-name-of-the-category`or `<next available three-digit number> - <name of the category>`
- A category consists of a directory and a `__categoryInfo.md` file
- Each category's `__categoryInfo.md` have to implement two meta tags, `isActive` and `titleEn`. Everything else, besides meta tags, is considered content and will be
parsed as such.
- The developer's documentation is only available in english!
- A `HowTo` is located in the `4-how-to` directory. Each of them has to implement the `titleEn` as well as an `metaDescriptionEn` meta tag. The latter is being used as a short description.

Directory structure
-------------------

Getting started
 : Entry area for new developers, guides for the installation, requirements, etc.
 
Internals
 : More like 'big picture' documentation. Describes the Shopware platform as a whole, consists of guides only.
 : Most likely the main part of the documentation you want to extend.
 
API
 : Everything API related.
 
HowTo
 : Specific problems and their solution, including an example plugins.
 
Community
 : Contribution guidelines, community related content.

Text styleguide
---------------

- Wording: Use "You" instead of "We".
- Do **not** use `h1` headings
- Hierarchize headings
- Use the [Markdown extended definition lists](https://www.markdownguide.org/extended-syntax/#definition-lists)
- Use crosslinks wherever possible
- Crosslinks have to start with a `./`
- Inline HTML is possible. Be careful with it though!
- Each `HowTo` consists of a title, a short description of the issue, the HowTo itself and the link to an example plugin
    - Difference Guide vs HowTo: The latter deals with a very specific problem, in short and targeted.

Resources
---------

  * [Documentation](https://developers.shopware.com)
  * [Contributing](https://developers.shopware.com/community/contributing-code/)
  * [Report issues](https://github.com/shopware/platform/issues) and
    [send Pull Requests](https://github.com/shopware/platform/pulls)
    in the [main Shopware\Core repository](https://github.com/shopware/platform)