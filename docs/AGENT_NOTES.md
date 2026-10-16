# Epay: decision-note workflow

The [pinned project-local Skill](../.agents/skills/write-notes-like-deepseek/UPSTREAM.md) records rationale alongside code. It does not replace existing project instructions or architecture documents. The repository is self-contained: no absolute path to another checkout or global Codex memory is required.

## Working sequence

1. State the business outcome. Read [the capability map](FEATURE_MAP.md), verify current code and permissions, and inspect related UI/API/data paths before choosing a solution.
2. Search active proposed, implemented and rejected notes. Reuse an existing note when only its facts change. Record new non-trivial decisions with the [proposal template](../.agents/skills/write-notes-like-deepseek/templates/proposed.md), including evidence of existing capabilities, affected entry points, real alternatives and acceptance criteria.
3. Proceed within existing user authorization. Imported confirmation defaults do not introduce repeated approval requirements. Missing material decisions still need user input; proposed notes do not themselves authorize deployment.
4. Once implementation and relevant checks are complete, rewrite Proposal as Decision and acceptance/risks as actual validation/consequences; move to implemented in the same commit as code. Decision reversals get new notes with links. Archive only eligible superseded implemented notes, not abandoned proposals.
5. Run the note checks and the existing tests/build required for the changed area. Update affected map entries. Mechanical edits do not require new notes; do not retroactively invent historical decisions.

## Layout and commands

Notes live under `.agents/notes/{proposed,implemented,rejected,archived}/{class}/yyyy-mm-dd-topic.md`. Classes are feature, bug-fix, simplification, architecture, process and testing. Create directories as needed. No central note index is required; the capability map indexes code, not individual notes.

Python 3.9+ checks the project-decision-kit installation manifest; Bun executes the pinned TypeScript tooling (CI: Python 3.12 / Bun 1.3.14). Both are development/CI dependencies only. From any directory, invoke the shell script by its path; other commands below run from the repository root:

```sh
bash .agents/verify-notes.sh
python3 .agents/workflow.py doctor
python3 .agents/workflow.py verify
bun .agents/skills/write-notes-like-deepseek/scripts/archive-agent-note.ts <old-note> --superseded-by <new-note>
bun .agents/skills/write-notes-like-deepseek/scripts/build-board.ts --bundle .agents/notes /tmp/decision-board.html 'Project decisions'
```

The archive command mutates files; inspect the diff and inbound links. The optional board bundles note content, so keep private notes and credentials out of published artifacts. Do not commit generated boards or real customer data, passwords, tokens or private keys.

## project-decision-kit installation and upgrades

Installed from the complete [project-decision-kit-v1.0.1 Release](https://github.com/nanashiwang/new-api/releases/tag/project-decision-kit-v1.0.1) on 2026-10-17. The ZIP SHA256 was verified against the release's SHA256SUMS.txt:

```text
0c7d0a40c18040667d486eb01e21ea9e16907ac2177540a4c72bb067de2a17c8
```

The first preview refused one conflict: the release's `payload/write-notes-like-deepseek/UPSTREAM.md` describes the source project's installation and `make verify-notes` command. Epay's existing [UPSTREAM.md](../.agents/skills/write-notes-like-deepseek/UPSTREAM.md) records its own reviewed import and shell entry point. After review, the complete package's working copy retained Epay's file byte-for-byte; the installer and the other 20 Skill files were unchanged. Preview then succeeded before `--apply`. No conflict checks were disabled and no previous-install hashes were fabricated.

The generated [.agents/workflow-install.json](../.agents/workflow-install.json) records version 1.0.1 and the actual SHA256 of all 22 managed files, including that Epay provenance adaptation. The original AGENTS.md remains an unchanged prefix followed by the installer's marked block. Existing maps, guides and all 14 notes are retained. The shell wrapper now calls `workflow.py verify`, so the existing CI checks managed-file integrity and rules as well as note structure. No second CI workflow is needed.

For a new computer, clone this repository, install Python 3.9+ and Bun, then run `doctor` and the shell wrapper above. The installed `workflow.py` supports `doctor` / `verify` without the download directory or a global Skill. Installation and upgrades require a new complete release package (including VERSION, payload and templates); do not use the installed single Python file as a distribution package.

For an upgrade, verify the new ZIP checksum and compare the package with the current managed files, especially UPSTREAM.md. Preserve Epay's provenance and entry-point guidance in the reviewed package working copy before running its `workflow.py install <project>` preview and then the same command with `--apply`. Review the preview even when the manifest authorizes an update: a stock package can replace a previously recorded local adaptation. Never erase the manifest, invent hashes or remove project rules to pass installation. Keep the installer, manifest, local templates, scripts, notes and documentation in Git; review any ignored file before using targeted `git add -f`.

Daily use needs no installation command: state the business goal, follow the existing capability-review sequence, update the owner note when appropriate, then run `bash .agents/verify-notes.sh` and the affected business checks. Epay has no root Makefile; `make verify-notes` from source-project documentation is not an Epay command.

## Verification boundaries

The independent Verify Agent Notes CI job runs on main pushes and pull requests, with full Git history. Archive comparison uses the pre-push commit or PR base, not the new HEAD. The unified verifier clears an inherited `AGENT_NOTE_ROOT`, so checks target this checkout's notes. CI failure does not automatically configure branch protection; no branch protection changes are included here.

Checks enforce directory/format/link and archive-seal integrity, not completeness of product research, truth of alternatives, or optimality of a design. Existing business tests, builds, release checks and production acceptance remain separate. Preserve PHP/MySQL runtime and current deployment scripts. Note checking uses Bun only on developer machines/CI; no production container changes are required.
