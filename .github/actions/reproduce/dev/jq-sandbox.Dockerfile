# Sandbox image for the http executor's response-field extraction. The assertion `field` is an
# agent-authored jq PROGRAM (not a passive path), and jq's env/$ENV builtins would otherwise read the
# runner's environment and leak a secret into the public verdict comment. We therefore run jq inside
# this tiny image with `--network none` and no env passed through, so the filter can only ever reach
# the response body piped in on stdin. Only jq comes from the image; the body arrives via stdin and
# nothing is mounted. Built at verify time by the "Arm … http sandbox" step (not on any hot path).
FROM alpine:3.20
RUN apk add --no-cache jq
