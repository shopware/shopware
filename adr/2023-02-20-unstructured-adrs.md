---
title: Unstructured ADRs
date: 2023-02-23
area: core
tags: [adr, file-structure, workflow]
---

## Context
Currently, our ADRs are grouped into folders according to different areas. We have added this structure to help people find their way around when they are looking for specific ADRs.

However, there are different approaches when searching for ADRs:
1) I am looking for an ADR and I know the headline.
2) I would like to read the latest ADRs
3) I would like to see ADRs for a specific area.
4) ...

The structuring by areas is good for case 3, but not for the other two. In the first case, the user can easily use the search via the directory to use the appropriate ADR, but will also get duplicate matches. Depending on the keyword the result can be quite long.

In the second case, I would like to read the latest ADRs, the user would have to go to git history, which is only possible via shell or IDE. 

## Decision

We will remove the area structure on directory level and introduce front matter in our ADRs as we already do in our changelog files. This makes it possible to extract metadata from content. Additionally, it can be used as groundwork, if we want to release our ADRs in different front-ends (handbook, dev-docs) and use the front-end's search functionalities.
With front matter and the flat file structure, you can solve all three problems described in this ADR. An example of this specific ADR would be:

```markdown
---
title: Unstructured ADRs
date: 2023-02-23
area: Product Operation #I added prod-ops because this is a workflow topic
tags: [ADR, file structure, workflow]
---
```

We also hope that this will make it easier for people to submit ADRs without being confused about which folder to put the file in.
