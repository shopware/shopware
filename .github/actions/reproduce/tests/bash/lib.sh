# Tiny assertion helpers for the bash-script tests (no external `bats` dependency, so it runs
# anywhere plain bash does). Each *.test.sh sources this, makes assertions, then calls `finish`.
_T_PASS=0
_T_FAIL=0

assert_eq() { # actual expected message
  if [ "$1" = "$2" ]; then
    _T_PASS=$((_T_PASS + 1)); printf '  ok   %s\n' "$3"
  else
    _T_FAIL=$((_T_FAIL + 1)); printf '  FAIL %s\n       expected: %s\n       actual:   %s\n' "$3" "$2" "$1"
  fi
}

assert_contains() { # haystack needle message
  case "$1" in
    *"$2"*) _T_PASS=$((_T_PASS + 1)); printf '  ok   %s\n' "$3" ;;
    *) _T_FAIL=$((_T_FAIL + 1)); printf '  FAIL %s\n       missing: %s\n       in:      %s\n' "$3" "$2" "$1" ;;
  esac
}

finish() {
  printf '  (%d passed, %d failed)\n' "$_T_PASS" "$_T_FAIL"
  [ "$_T_FAIL" -eq 0 ]
}
