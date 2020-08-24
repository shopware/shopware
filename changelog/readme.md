For every code change there has to be a changelog markdown file in `/changelog` directory which follows the following naming convention:

{YYYY-MM-DD}-Meaningful-title-of-the-change.md  
**Example**: 2020-08-03-New-CMS-components-for-3D-content.md

In this file all necessary changes are documented. The content of the file should always use the template which can be found in the `/changelog/_template.md` file. You can see a full example in the `/changelog/_example.md` file for a better understanding. Here is detailed description of the meta information:

*  `title` (Required): Add a meaningful title. It can match the file name.  
 
*  `issue` (Required): Add the corresponding Jira issue key. Can be the key of a single ticket or the key of an epic.  

*  `author`: This field is optional for shopware employees, but required for all external developers. It can be used to identify the author of code changes, or to display the name in a changelog.  

*  `author_email`: This field is optional for shopware employees, but required for all external developers.  

*  `author_github` (Optional): This is also mainly intended for external developers to get some reputation of your GitHub profile.  

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

There should be no entry without one of the listed keywords. The section for have a look into the `/changelog/_example.md` file for some examples.
