For every feature or important code change there has to be a changelog markdown file in `/changelog/_unreleased` directory which follows the following naming convention:

{YYYY-MM-DD}-Meaningful-title-of-the-change.md  
**Example**: 2020-08-03-New-CMS-components-for-3D-content.md

In the file all necessary changes are documented. The content of the file should always use the template which can be found in the `/changelog/_template.md` file. You can see a full example in the `/changelog/_example.md` file for a better understanding. 

## Meta Information
Next to the changelog information it is important to add the necessary meta information about your changes. Here is a detailed description of the meta information:

*  `title` (Required): Add a meaningful title. It can match the file name.  
 
*  `issue` (Required): Add the corresponding Jira issue key. Can be the key of a single ticket or the key of an epic.  

*  `author`: This field is optional for shopware employees, but required for all external developers. It can be used to identify the author of code changes, or to display the name in a changelog.  

*  `author_email`: This field is optional for shopware employees, but required for all external developers.  

*  `author_github` (Optional): This is also mainly intended for external developers to get some reputation of your GitHub profile.  

If you do not fill out some of the fields, please remove them from the file.

## Changelog Entries
In the content of the file you add the technical changelog and upgrade information. The content is divided by first class headlines in the five categories:

*  Core
*  API
*  Administration
*  Storefront
*  Upgrade Information

In the sections under the headlines you add the corresponding information as list entries, except the upgrade information. For each entry you have to use the corresponding keyword at the beginning of each entry: 

*  Added
*  Removed
*  Refactored
*  Deprecated

There should be no entry without one of the listed keywords. This is important to have a good consistency and also should it make you think about a meaningful description of your changes. So for example we do not allow the keyword "Fixed" as it leads to poor descriptions.

**Example:**
```
Bad changelog:
*  Fixed data binding issues in CMS elements.
Good changelog:
*  Changed method `registerCmsElement()` in `module/sw-cms/service/cms.service.js` to fix the mapping of the element data.
```

Have a look into the `/changelog/_example.md` file for some examples.

## Upgrade Information
In the section for the upgrade information you add documentation in markdown for each part of your changes. Please use second class headlines in markdown (`## `) to create new sections inside the upgrade information. You can use the content of `/changelog/_template.md` file as a start.
