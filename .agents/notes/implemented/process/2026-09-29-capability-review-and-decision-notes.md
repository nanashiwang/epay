# Agent Note: Capability review and repository decision notes

Status: implemented

## Problem

Page-local changes can duplicate existing capabilities; chat-only reasoning does not travel with the repository. Changes need evidence from adjacent entry points and durable rationale without replacing existing project rules. A vendored Skill alone also lacks an installation inventory: future upgrades need to distinguish reviewed tool files from local adaptations.

## Existing capabilities and impact

Epay has 14 existing decision notes, a capability map, the project-local Skill, a shell verification entry point and Verify Agent Notes CI. They remain the owner of this workflow; a second note tree or CI job would duplicate existing responsibilities. The current code audit follows payment entry, channel selection, tenant APIs, settlement, subscriptions, operations, public help and deployment checks through their implementation and test files. Existing architecture and product documentation remain authoritative. Only development tooling, its CI and documentation are affected.

## Decision

The repository uses project-decision-kit 1.0.1 to manage the existing pinned Skill and validate it before running the Bun note checks. The complete release ZIP was checksum-verified; its installer and 20 matching Skill files remain unchanged. The only differing payload file, UPSTREAM.md, retains Epay's reviewed import history and shell command in the package working copy. Preview succeeds before installation, and the installer generates actual hashes for all 22 managed files. This preserves provenance without disabling conflict checks or fabricating prior installation state. Package identity and the adaptation procedure are recorded in docs/AGENT_NOTES.md.

The original AGENTS.md is preserved as a prefix with one managed block appended. The existing shell wrapper delegates to `python3 .agents/workflow.py verify`; existing CI uses that wrapper with Python 3.12 and Bun 1.3.14. Both new integrity checks and the original tree/format/archive checks therefore run through one entry point. Existing capability maps and notes are project-owned and are not replaced by package templates. Record decisions, not invented historical explanations; continue within existing authorization instead of adding routine confirmation gates.

## Alternatives considered

- Chat reminders alone are cheap but cannot preserve decisions alongside source changes.
- Installing only the Skill preserves rationale but cannot discover overlooked product capabilities; require code and adjacent-entry review as well.
- A shared absolute path saves duplicated tooling but breaks standalone clones and CI; vendor a fixed copy into each repository.
- Replacing UPSTREAM.md with the stock package simplifies byte-for-byte distribution but loses Epay's import evidence and advertises a root Makefile command that Epay does not have. Preserve the project provenance in the reviewed package copy and document that one adaptation.
- Keeping CI on direct Bun commands avoids Python but omits the new installation-manifest and managed-rule checks. Delegate the compatible wrapper to the unified verifier instead of maintaining two verification implementations or adding a parallel workflow.

## Verification

On 2026-10-17, `doctor`, direct `verify` and the shell wrapper pass with Python 3.9.6 / Bun 1.3.14: 14 notes pass tree, format and relative-link checks; the archive currently has zero notes and zero seals. Documentation link targets, Python syntax, shell syntax and `git diff --check` pass. The original AGENTS.md prefix and all existing Skill files, including UPSTREAM.md, are unchanged.

Isolated temporary fixtures verify standalone operation with a path containing spaces, invocation from outside the checkout, idempotent adapted reinstallation, rejection of modified managed tools/rules, and conflict refusal without partial writes. Missing alternatives and broken note links fail verification; setting AGENT_NOTE_ROOT to an empty external directory cannot bypass the wrapper. The earlier archive-seal and Git-baseline regression evidence is retained in this note's Git history; no new archived note was created in this integration.

The capability map now records actual API orchestration, service/data ownership and corresponding business tests/CI. PHP is absent and the Docker daemon is unavailable on this developer machine, so PHP/MySQL/Docker business suites were not rerun locally for this tooling-only change. No live payment or production acceptance is inferred. Remote workflow success is checked against the pushed commit separately from local validation.

## Consequences

Maps can become stale and need source verification. Structural checks do not establish design quality or guarantee complete note coverage. Python 3.9+ and Bun are development-only prerequisites, not application dependencies. The install manifest must travel with the tools: losing it removes the baseline for safe upgrades. An upgrade can replace a previously recorded local adaptation, so UPSTREAM.md must be compared before every apply even if the installer reports no conflict.

The benefit is searchable rationale, explicit adjacent-capability review and a verifiable portable installation. The maintenance cost is keeping maps, notes and the provenance adaptation aligned with code and future packages. This repository has test/build CI but no application release/tag/asset publishing workflow; the integration records the tool version without inventing an Epay release process or deploying services.
