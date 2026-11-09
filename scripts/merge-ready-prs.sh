#!/usr/bin/env bash
#
# Bubba Hub consolidated GitHub PR manager
# Scans bubbashub1/bubbaplugin, bubbashub1/bubbawp, bubbashub1/bubbatheme.
# Default is read-only. Merging requires --execute and typing MERGE.
# Requirements: gh, jq. Authentication: gh auth login
#
# Strict policy:
# - Only open, non-draft PRs targeting a protected base branch.
# - Classic branch protection must expose required status checks.
# - Every required check must have a successful check-run or commit status.
# - No CHANGES_REQUESTED review; required approvals must be satisfied.
# - PR must be CLEAN, mergeable, and up-to-date.
# - Revalidates all gates and the head SHA immediately before merging.
# - Squash merge only; asks GitHub to delete the source branch.
#
# This fails closed if classic branch-protection data cannot be read.
# Repositories using rulesets only need explicit ruleset support added.

set -Eeuo pipefail

MODE="--dry-run"
case "\${1:---dry-run}" in
  --dry-run|--execute) MODE="\${1:---dry-run}" ;;
  -h|--help) sed -n '2,25p' "$0"; exit 0 ;;
  *) echo "Usage: $0 [--dry-run|--execute]" >&2; exit 2 ;;
esac

for cmd in gh jq; do
  command -v "$cmd" >/dev/null 2>&1 || { echo "ERROR: Required command not found: $cmd" >&2; exit 1; }
done
gh auth status >/dev/null 2>&1 || { echo "ERROR: Run gh auth login first." >&2; exit 1; }

REPOS=("bubbashub1/bubbaplugin" "bubbashub1/bubbawp" "bubbashub1/bubbatheme")

if [[ "$MODE" == "--execute" ]]; then
  [[ -t 0 ]] || { echo "ERROR: --execute requires an interactive terminal." >&2; exit 1; }
  echo "Execution mode: eligible PRs may be merged."
  read -r -p 'Type MERGE to continue: ' confirmation
  [[ "$confirmation" == "MERGE" ]] || { echo "Cancelled. No changes made."; exit 0; }
else
  echo "DRY RUN: read-only scan; no PRs or branches will be changed."
fi

TOTAL=0; READY_COUNT=0; BLOCKED_COUNT=0; FLAGGED_COUNT=0
MERGED_COUNT=0; FAILED_COUNT=0; SKIPPED_COUNT=0

PR_READY=false; PR_MANUAL=false; PR_HEAD_SHA=""; PR_TITLE=""; PR_REASON=""

evaluate_pr() {
  local repo="$1" number="$2" phase="$3"
  local pr protection required_rows required_count approvals
  local base merge_state mergeable draft decision
  local check_runs statuses context app_id run_count conclusion status_state
  local failures=0

  PR_READY=false; PR_MANUAL=false; PR_HEAD_SHA=""; PR_TITLE=""; PR_REASON=""
  echo
  echo "[$repo] PR #$number — $phase"

  if ! pr=$(gh pr view "$number" --repo "$repo" --json title,isDraft,headRefOid,baseRefName,mergeStateStatus,mergeable,reviewDecision 2>&1); then
    echo "  BLOCK: Cannot read PR details: $pr"
    PR_REASON="PR details unavailable"; return 0
  fi

  PR_TITLE=$(jq -r '.title // "(untitled)"' <<<"$pr")
  PR_HEAD_SHA=$(jq -r '.headRefOid // empty' <<<"$pr")
  base=$(jq -r '.baseRefName // empty' <<<"$pr")
  draft=$(jq -r '.isDraft // false' <<<"$pr")
  merge_state=$(jq -r '.mergeStateStatus // "UNKNOWN"' <<<"$pr")
  mergeable=$(jq -r '.mergeable // false' <<<"$pr")
  decision=$(jq -r '.reviewDecision // "NONE"' <<<"$pr")

  echo "  Title: $PR_TITLE"
  echo "  Base: $base | Merge state: $merge_state | Review: $decision"

  if [[ "$draft" == "true" ]]; then
    echo "  SKIP: Draft PR."; PR_REASON="Draft"; ((SKIPPED_COUNT+=1)); return 0
  fi
  if [[ -z "$PR_HEAD_SHA" || -z "$base" ]]; then
    echo "  BLOCK: Missing head SHA or base branch."; PR_REASON="Incomplete PR metadata"; return 0
  fi

  if ! protection=$(gh api "repos/$repo/branches/$base/protection" 2>/dev/null); then
    echo "  BLOCK: Cannot verify classic branch protection for '$base'."
    echo "         Configure branch protection or add verified ruleset support."
    PR_REASON="Branch protection unavailable"; return 0
  fi

  approvals=$(jq -r '.required_pull_request_reviews.required_approving_review_count // 0' <<<"$protection")
  required_rows=$(jq -r '
    [
      ((.required_status_checks.contexts // [])[] | [., ""]),
      ((.required_status_checks.checks // [])[]? | [.context, ((.app_id // "") | tostring)])
    ] | unique | .[] | @tsv
  ' <<<"$protection")
  required_count=$(jq -r '
    ((.required_status_checks.contexts // []) | length) +
    ((.required_status_checks.checks // []) | length)
  ' <<<"$protection")

  if [[ "$required_count" -eq 0 || -z "$required_rows" ]]; then
    echo "  BLOCK: No required CI status checks are configured/reported."
    PR_REASON="No required CI checks"; return 0
  fi

  if ! check_runs=$(gh api "repos/$repo/commits/$PR_HEAD_SHA/check-runs?per_page=100" --jq '.check_runs // []' 2>/dev/null); then
    echo "  BLOCK: Unable to retrieve check runs."; PR_REASON="Check runs unavailable"; return 0
  fi
  if ! statuses=$(gh api "repos/$repo/commits/$PR_HEAD_SHA/status?per_page=100" --jq '.statuses // []' 2>/dev/null); then
    echo "  BLOCK: Unable to retrieve commit statuses."; PR_REASON="Commit statuses unavailable"; return 0
  fi

  echo "  Required CI checks:"
  while IFS=$'\t' read -r context app_id; do
    [[ -n "$context" ]] || continue
    if [[ -n "$app_id" ]]; then
      run_count=$(jq --arg n "$context" --arg a "$app_id" '[.[] | select(.name == $n and ((.app.id // -1) | tostring) == $a)] | length' <<<"$check_runs")
      conclusion=$(jq -r --arg n "$context" --arg a "$app_id" '[.[] | select(.name == $n and ((.app.id // -1) | tostring) == $a)] | sort_by(.id) | last | .conclusion // "MISSING"' <<<"$check_runs")
    else
      run_count=$(jq --arg n "$context" '[.[] | select(.name == $n)] | length' <<<"$check_runs")
      conclusion=$(jq -r --arg n "$context" '[.[] | select(.name == $n)] | sort_by(.id) | last | .conclusion // "MISSING"' <<<"$check_runs")
    fi

    if [[ "$run_count" -gt 0 ]]; then
      if [[ "$conclusion" == "success" ]]; then
        echo "    PASS: $context"
      else
        echo "    FAIL/PENDING: $context ($conclusion)"; ((failures+=1))
        if [[ "$context" =~ [Ll]int|[Ff]ormat|[Pp]rettier|[Bb]lack|[Ss]tyle|[Cc]heckstyle ]]; then
          PR_MANUAL=true; echo "    FLAG: likely formatting/lint remediation."
        fi
      fi
    elif [[ -z "$app_id" ]]; then
      status_state=$(jq -r --arg n "$context" '[.[] | select(.context == $n)] | sort_by(.updated_at) | last | .state // "MISSING"' <<<"$statuses")
      if [[ "$status_state" == "success" ]]; then
        echo "    PASS: $context (commit status)"
      else
        echo "    FAIL/PENDING: $context ($status_state)"; ((failures+=1))
        if [[ "$context" =~ [Ll]int|[Ff]ormat|[Pp]rettier|[Bb]lack|[Ss]tyle|[Cc]heckstyle ]]; then
          PR_MANUAL=true; echo "    FLAG: likely formatting/lint remediation."
        fi
      fi
    else
      echo "    FAIL: $context (required GitHub App check not found)"; ((failures+=1))
    fi
  done <<<"$required_rows"

  if [[ "$failures" -gt 0 ]]; then
    echo "  BLOCK: $failures required check(s) have not passed."
    PR_REASON="Required CI checks incomplete or failed"; return 0
  fi

  case "$decision" in
    CHANGES_REQUESTED)
      echo "  BLOCK: A reviewer has requested changes."; PR_REASON="Changes requested"; return 0 ;;
    REVIEW_REQUIRED)
      echo "  BLOCK: Required review approval is outstanding."; PR_REASON="Review approval outstanding"; return 0 ;;
    APPROVED|NONE)
      if [[ "$approvals" -gt 0 && "$decision" != "APPROVED" ]]; then
        echo "  BLOCK: Branch protection requires $approvals approval(s)."
        PR_REASON="Required approval missing"; return 0
      fi
      echo "  PASS: No blocking review decision." ;;
    *)
      echo "  BLOCK: Unrecognised review decision: $decision"; PR_REASON="Review state uncertain"; return 0 ;;
  esac

  if [[ "$merge_state" != "CLEAN" || "$mergeable" != "true" ]]; then
    case "$merge_state" in
      BEHIND) echo "  BLOCK: PR branch is behind the base branch."; PR_REASON="Branch behind base" ;;
      DIRTY) echo "  BLOCK: Merge conflicts detected; resolve manually."; PR_MANUAL=true; PR_REASON="Merge conflicts" ;;
      *) echo "  BLOCK: GitHub does not confirm a clean, up-to-date merge ($merge_state)."; PR_REASON="Mergeability not confirmed" ;;
    esac
    return 0
  fi

  echo "  PASS: Clean, mergeable, and up-to-date."
  PR_READY=true; PR_REASON="All strict gates passed"
}

echo
echo "============================================================"
echo " BUBBA HUB — CONSOLIDATED PULL REQUEST REPORT"
echo "============================================================"

for repo in "\${REPOS[@]}"; do
  echo
  echo "REPOSITORY: $repo"
  if ! pr_numbers=$(gh pr list --repo "$repo" --state open --limit 1000 --json number --jq '.[].number' 2>&1); then
    echo "  ERROR: Could not list PRs: $pr_numbers"; ((FAILED_COUNT+=1)); continue
  fi
  if [[ -z "$pr_numbers" ]]; then echo "  No open pull requests."; continue; fi

  while IFS= read -r number; do
    [[ -n "$number" ]] || continue
    ((TOTAL+=1))
    evaluate_pr "$repo" "$number" "initial scan"

    if [[ "$PR_READY" != "true" ]]; then
      [[ "$PR_MANUAL" == "true" ]] && ((FLAGGED_COUNT+=1))
      [[ "$PR_REASON" == "Draft" ]] || ((BLOCKED_COUNT+=1))
      continue
    fi

    ((READY_COUNT+=1))
    if [[ "$MODE" == "--dry-run" ]]; then
      echo "  READY: would squash-merge and request source-branch deletion."
      continue
    fi

    original_sha="$PR_HEAD_SHA"
    evaluate_pr "$repo" "$number" "final pre-merge revalidation"
    if [[ "$PR_READY" != "true" || "$PR_HEAD_SHA" != "$original_sha" ]]; then
      echo "  BLOCK: PR changed or no longer passes validation; not merging."
      ((BLOCKED_COUNT+=1)); continue
    fi

    echo "  MERGING: squash..."
    if gh pr merge "$number" --repo "$repo" --squash --delete-branch --match-head-commit "$original_sha"; then
      echo "  MERGED: PR #$number. Source branch deletion requested."; ((MERGED_COUNT+=1))
    else
      echo "  ERROR: GitHub refused to merge PR #$number."; ((FAILED_COUNT+=1))
    fi
  done <<<"$pr_numbers"
done

echo
echo "============================================================"
echo " SUMMARY"
echo "============================================================"
printf 'Open PRs scanned:       %s\n' "$TOTAL"
printf 'Passed all gates:       %s\n' "$READY_COUNT"
printf 'Blocked:                %s\n' "$BLOCKED_COUNT"
printf 'Manual attention flags: %s\n' "$FLAGGED_COUNT"
printf 'Merged:                 %s\n' "$MERGED_COUNT"
printf 'Operational failures:   %s\n' "$FAILED_COUNT"
printf 'Drafts skipped:         %s\n' "$SKIPPED_COUNT"
echo "Mode: $MODE"
[[ "$MODE" == "--dry-run" ]] && echo "No changes made. Review this report before using --execute."
