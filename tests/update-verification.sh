#!/bin/bash
# No Docker, git, network or production files: execute real update functions with stubs.
set -eu
repo_dir=$(pwd)
fixture=$(mktemp -d)
trap 'rm -r "$fixture"' EXIT
sed '/^case "${1:-}" in/,$d' epay.sh > "$fixture/functions.sh"
mkdir -p "$fixture/includes"
cp includes/common.php "$fixture/includes/common.php"
cat > "$fixture/run.sh" <<'EOF'
#!/bin/bash
source "$1/functions.sh"
git() {
 case "$1" in
  rev-parse) return 0;;
  branch) echo main;;
  diff) [ "$SCENARIO" != dirty ];;
  pull) [ "$SCENARIO" != pullfail ];;
  *) return 1;;
 esac
}
cmd_backup(){ [ "$SCENARIO" != backupfail ]; }
sh(){ [ "$SCENARIO" != keyfail ]; }
compose_stub(){
 case "$1" in
  up) [ "$SCENARIO" != buildfail ];;
  exec) [ "$SCENARIO" != verifyfail ];;
  *) return 1;;
 esac
}
COMPOSE_CMD=compose_stub
cmd_update
EOF
for scenario in dirty pullfail backupfail keyfail buildfail verifyfail; do
 if SCENARIO="$scenario" bash "$fixture/run.sh" "$fixture" > "$fixture/output" 2>&1; then echo "Unexpected success: $scenario";exit 1;fi
 if grep -q '更新并验收完成' "$fixture/output"; then echo "False success: $scenario";exit 1;fi
done
SCENARIO=ok bash "$fixture/run.sh" "$fixture" > "$fixture/output" 2>&1
grep -q '更新并验收完成' "$fixture/output"
echo 'Update verification: 7 failure/success paths passed'
