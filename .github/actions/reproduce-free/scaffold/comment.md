<!--
  This template becomes the body of the issue comment. Keep the structure below unless you have a
  good reason — it is the house style. The {{…}} placeholders are resolved by the harness FROM THE
  TRUSTED RUNS after you stop: you reference facts, you cannot author them.

    {{file:<path>}}                          an authored file, shown as a code block
    {{run:<leg>:output}}                     the trusted leg's run.sh output (tail, collapsed)
    {{run:<leg>:exit}} / {{run:<leg>:status}}
    {{run:<leg>:observed}} / {{run:<leg>:expected}}   your ##repro marker values from that leg
    {{run:<leg>:steps}}                      your ##repro step narration as a list
    {{evidence:<leg>:<file>}}                a file your run saved to $EVIDENCE_DIR on that leg
    {{diff:shop}}                            what your run changed inside the provisioned shop

  <leg> is `reported` or `trunk`. Unresolved placeholders render as "(not produced)".
  Every file you changed but never reference via {{file:…}} is called out by the harness above
  this body — disclose everything. Preview with `repro render` before you stop.
-->

_(One or two sentences: what the bug is and how this reproduction demonstrates it.)_

### What the reproduction does

_(A few sentences: the setup it prepares, the behaviour it checks, and why that assertion
captures the reported symptom.)_

{{file:repro/run.sh}}

### On the reported version

- **Expected:** {{run:reported:expected}}
- **Observed:** {{run:reported:observed}}

{{run:reported:output}}

### On trunk

- **Observed:** {{run:trunk:observed}}

{{run:trunk:output}}
