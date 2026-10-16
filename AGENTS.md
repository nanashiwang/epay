# Epay — Project Instructions

Read README.md and the existing architecture/build documentation relevant to the task.


## Capability review and decision notes

- Start from the business outcome, not the first page or implementation mentioned. Use [the capability map](docs/FEATURE_MAP.md), then verify current UI/entry points, routes, permissions, services and data sources for the affected scope.
- Before implementation, briefly describe existing capabilities, real alternatives, reuse/extension/migration choices and affected entry points. Explain why an independent new implementation is needed. Do not expand small fixes into unrelated rewrites.
- For non-trivial behavior, architecture, contract or tooling decisions, use the [project-local Skill](.agents/skills/write-notes-like-deepseek/SKILL.md) and [workflow guide](docs/AGENT_NOTES.md). Search active notes first: `rg --hidden --glob '!**/archived/**' '<topic>' .agents/notes`. Update an existing owner note when its rationale still holds.
- New decisions start in proposed; move and rewrite them as implemented only after implementation and relevant verification, in the same commit as the change. Record genuine alternatives and costs; do not invent historical rationale or create notes for purely mechanical edits.
- User instructions and existing project rules take precedence over imported Skill defaults. Continue within existing authorization without repeated approval gates; ask only for material unresolved decisions or actions outside that scope. This workflow does not grant deployment, release or cross-project write permission.
- Validate complete affected user flows and adjacent entry points, including shared definitions, filtering, permissions and failure behavior where relevant. Structural note checks do not prove design quality or guarantee all important decisions were recorded.
- Update affected capability-map entries and notes alongside code. Run `bash .agents/verify-notes.sh` plus the existing checks required for the change. Existing architecture, language, build, release and subdirectory rules remain in force.

Project-specific context: Preserve PHP/MySQL runtime and current deployment scripts. Note checking uses Bun only on developer machines/CI; no production container changes are required.


<!-- project-workflow:start -->
## 能力核对与决策笔记

按任务范围从 docs/FEATURE_MAP.md 核对现有入口、权限、接口和数据来源，再设计；优先复用已有能力。
非平凡改动遵循 .agents/skills/write-notes-like-deepseek/SKILL.md；先搜索活跃笔记，已有归属优先更新，真实取舍才新建。
提案先 proposed，实施验证后转 implemented，与代码一起提交；机械改动免写。用户和本项目规则优先于 Skill 的默认交互流程，已有授权内自主推进。
完成时同步能力地图与相关笔记，执行 python3 .agents/workflow.py verify 和改动相关的项目检查。结构检查不代替业务验收。
提交、发布、部署遵循本项目原有授权。说明见 docs/AGENT_NOTES.md。
<!-- project-workflow:end -->
