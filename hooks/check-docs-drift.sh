#!/usr/bin/env bash
#
# client-docs drift notice.
#
# Fires after a file is edited. If that file is one that documentation depends
# on, it adds a single quiet reminder to the conversation.
#
# Three rules it will not break:
#   1. Dormant unless docs/.client-docs.yml exists. A project that never opted
#      in never hears from this plugin.
#   2. It notifies. It never edits, never blocks, never fails a tool call.
#   3. Once per session per category. A reminder on every save is noise, and
#      noise gets hooks uninstalled.

set -uo pipefail

# Never break the tool call that triggered us.
trap 'exit 0' ERR

payload=$(cat 2>/dev/null || true)
[ -n "$payload" ] || exit 0

read_json() {
  python3 -c '
import json,sys
try:
    d = json.load(sys.stdin)
except Exception:
    sys.exit(0)
keys = sys.argv[1].split(".")
for k in keys:
    if not isinstance(d, dict):
        sys.exit(0)
    d = d.get(k)
    if d is None:
        sys.exit(0)
print(d)
' "$1" 2>/dev/null <<<"$payload"
}

cwd=$(read_json cwd)
[ -n "${cwd:-}" ] || cwd=$PWD

# Rule 1: dormant without a manifest.
[ -f "$cwd/docs/.client-docs.yml" ] || exit 0

file=$(read_json tool_input.file_path)
[ -n "${file:-}" ] || file=$(read_json tool_input.notebook_path)
[ -n "${file:-}" ] || exit 0

# Repository-relative, so the patterns below are readable.
rel=${file#"$cwd"/}

# Never comment on the documentation itself, or on vendored code.
case "$rel" in
  docs/*|*/docs/*|README.md|CHANGELOG.md) exit 0 ;;
  vendor/*|node_modules/*|web/core/*|core/*|*/contrib/*|dist/*|build/*|.git/*) exit 0 ;;
esac

# Which documentation a change touches. Same mapping as /docs-update; keep the
# two in step.
category=""
docs=""
case "$rel" in
  composer.json|composer.lock|package.json|package-lock.json|bun.lock|bun.lockb|yarn.lock|pnpm-lock.yaml)
    category=dependencies; docs="INSTALLATION, DEVELOPMENT" ;;
  .env.example|.env.sample)
    category=configuration; docs="CONFIGURATION" ;;
  pantheon.yml|pantheon.upstream.yml|netlify.toml|vercel.json|Procfile|fly.toml)
    category=deployment; docs="DEPLOYMENT, CLIENT-HANDOFF" ;;
  .github/workflows/*|.gitlab-ci.yml|bitbucket-pipelines.yml|.circleci/*)
    category=ci; docs="DEPLOYMENT, DEVELOPMENT" ;;
  Dockerfile|docker-compose.yml|compose.yml|.lando.yml|.ddev/*)
    category=environment; docs="DEVELOPMENT" ;;
  vite.config.*|webpack.config.*|tailwind.config.*|rollup.config.*|next.config.*)
    category=build; docs="DEVELOPMENT" ;;
  config/*.yml|config/*/*.yml)
    category=drupal-config; docs="ARCHITECTURE, CLIENT-HANDOFF" ;;
  *.routing.yml|*.services.yml|*.permissions.yml|*.links.menu.yml)
    category=drupal-code; docs="ARCHITECTURE" ;;
  *modules/custom/*|*themes/custom/*|src/*|app/*|lib/*)
    category=source; docs="ARCHITECTURE" ;;
esac

[ -n "$category" ] || exit 0

# Rule 3: once per session per category.
session=$(read_json session_id)
[ -n "${session:-}" ] || session=nosession
state="${TMPDIR:-/tmp}/client-docs-$session"
mkdir -p "$state" 2>/dev/null || exit 0
marker="$state/$category"
[ -e "$marker" ] && exit 0
: > "$marker"

python3 -c '
import json,sys
print(json.dumps({
  "hookSpecificOutput": {
    "hookEventName": "PostToolUse",
    "additionalContext": (
      "client-docs: %s changed (%s), which affects %s. "
      "Mention /docs-update once at the end of this work. Do not run it now "
      "and do not interrupt what you are doing."
    ) % (sys.argv[1], sys.argv[2], sys.argv[3])
  }
}))
' "$rel" "$category" "$docs"

exit 0
