---
title: Allow deletion of users with statehistory entries
issue: 
author: Philipp Bucher
author_email: bucher@netzreich.de
author_github: @GerDner
---
# Core
* Changed Foreign-Key definition of fk.state_machine_history.user_id to "SET NULL" to allow deletion of users.
