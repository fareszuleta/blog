#!/usr/bin/env bash
# Usage: rerun.sh <runs> <test command...>
# Runs the test command <runs> times and saves each full output to a temp dir.
# Prints the directory, then one line per run with its exit code.
set -u
runs="${1:?usage: rerun.sh <runs> <test command...>}"; shift
[ "$#" -ge 1 ] || { echo "usage: rerun.sh <runs> <test command...>" >&2; exit 2; }
dir="$(mktemp -d "${TMPDIR:-/tmp}/flaky.XXXXXX")"
echo "logs: $dir"
for i in $(seq 1 "$runs"); do
  "$@" >"$dir/run-$i.log" 2>&1
  echo "run $i: exit $?"
done
