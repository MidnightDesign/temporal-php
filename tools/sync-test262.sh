#!/usr/bin/env bash
#
# Sync test262 Temporal test files from the upstream tc39/test262 repository.
#
# Usage:
#   ./tools/sync-test262.sh            # sync all implemented classes
#   ./tools/sync-test262.sh Duration   # sync only Duration
#
# This does a sparse checkout of just the Temporal directories we need,
# then rsyncs them into tests/Test262/data/.

set -euo pipefail

REPO_URL="https://github.com/tc39/test262.git"
CLONE_DIR="$(mktemp -d)"
DATA_DIR="$(cd "$(dirname "$0")/../tests/Test262/data" && pwd)"

# All Temporal classes we track
ALL_CLASSES=(
    Duration
    Instant
    Now
    PlainDate
    PlainDateTime
    PlainMonthDay
    PlainTime
    PlainYearMonth
    ZonedDateTime
)

# If args given, sync only those classes; otherwise sync all
if [[ $# -gt 0 ]]; then
    CLASSES=("$@")
else
    CLASSES=("${ALL_CLASSES[@]}")
fi

cleanup() {
    rm -rf "$CLONE_DIR"
}
trap cleanup EXIT

echo "==> Cloning test262 (sparse, depth=1)..."
git clone --depth 1 --filter=blob:none --sparse "$REPO_URL" "$CLONE_DIR" 2>&1 | tail -1

cd "$CLONE_DIR"

# Set up sparse checkout for just the Temporal dirs we need
SPARSE_PATHS=()
for class in "${CLASSES[@]}"; do
    SPARSE_PATHS+=("test/built-ins/Temporal/$class")
done

git sparse-checkout set "${SPARSE_PATHS[@]}" 2>/dev/null

echo "==> Syncing test files..."

total_added=0
total_removed=0

for class in "${CLASSES[@]}"; do
    src="$CLONE_DIR/test/built-ins/Temporal/$class"
    dst="$DATA_DIR/$class"

    if [[ ! -d "$src" ]]; then
        echo "    WARN: $class not found in upstream repo, skipping"
        continue
    fi

    # Count files before
    before=$(find "$dst" -name '*.js' 2>/dev/null | wc -l)

    # Sync: add new files, update changed files, remove files deleted upstream
    mkdir -p "$dst"
    rsync -rl --no-group --no-owner --delete --include='*/' --include='*.js' --exclude='*' "$src/" "$dst/"

    # Count files after
    after=$(find "$dst" -name '*.js' | wc -l)

    added=$((after - before))
    if [[ $added -gt 0 ]]; then
        echo "    $class: $before -> $after (+$added)"
        total_added=$((total_added + added))
    elif [[ $added -lt 0 ]]; then
        removed=$((-added))
        echo "    $class: $before -> $after (-$removed removed)"
        total_removed=$((total_removed + removed))
    else
        echo "    $class: $after (up to date)"
    fi
done

echo ""
echo "==> Done. Added: $total_added, Removed: $total_removed"

if [[ $total_added -gt 0 || $total_removed -gt 0 ]]; then
    echo ""
    echo "Next steps:"
    echo "  1. Run: composer test262:build"
    echo "  2. Run: composer test262:run"
    echo "  3. Review and commit the changes"
fi
