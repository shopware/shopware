# Assembles base-adoption-input.json from the tab-separated work files and the scope in $ENV.
def rows: split("\n") | map(select(length > 0) | split("\t"));
def nullable: if . == "" then null else . end;

($controls | rows | map({key: (.[0] + "\t" + .[1]), value: {
    base_before_hits: (.[2] | tonumber),
    base_after_hits: (.[3] | tonumber),
    owned_hits: (.[4] | tonumber),
    owned_symbol_hits: (.[5] | tonumber)
}}) | from_entries) as $control
| ($tokens | rows
    | map({id: .[0], token: .[1], sites: (.[2] | tonumber)} + $control[.[0] + "\t" + .[1]])
    | group_by(.id) | map({key: .[0].id, value: (sort_by(-.sites, .token) | map(del(.id)))}) | from_entries) as $token
| ($candidates | rows
    | map({id: .[0], token: .[1], path: .[2], line: (.[3] | tonumber), text: (.[4] // "")})
    | group_by(.id) | map({key: .[0].id, value: (sort_by(.token, .path, .line) | map(del(.id)))}) | from_entries) as $candidate
| ($generated | rows | group_by(.[0]) | map({key: .[0][0], value: (map(.[1]) | sort)}) | from_entries) as $generated_owned
| ($items | rows
    | map({id: .[0], class: .[1], commit: .[2], commits: (.[6] | split(",")), path: .[3], detail: .[4], rank: (.[5] | tonumber)})
    | map(. + {before_tokens: ($token[.id] // []), candidates: ($candidate[.id] // []), generated_owned: ($generated_owned[.id] // [])})
    | sort_by(.class, -.rank, .id)) as $item_list
| ($item_list | map(select(.class == "gate") | .detail) | unique) as $gate_ids
| {
    schema: 1,
    scope: {
        mode: $ENV.MODE,
        head: $ENV.HEAD_SHA,
        base: $ENV.BASE_SHA,
        merge: ($ENV.MERGE_SHA | nullable),
        range_from: ($ENV.RANGE_FROM | nullable),
        range_to: ($ENV.RANGE_TO | nullable),
        commit_count: ($ENV.COMMIT_COUNT | tonumber),
        owned_count: ($owned | rows | length),
        trial_merge: $ENV.TRIAL_MERGE
    },
    conflicts: ($conflicts | rows | map(.[0])),
    owned_files: ($owned | rows | map(.[0])),
    items: $item_list,
    gate_plan: ($gates | rows | .[1:]
        | map(select(.[1] as $id | $gate_ids | any(. == $id)))
        | group_by(.[1]) | map(.[0] | {gate_id: .[1], command: .[2], extensions: (.[3] | split(","))}))
}
