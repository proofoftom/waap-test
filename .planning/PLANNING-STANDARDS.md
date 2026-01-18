# Planning Standards

This project uses a comprehensive planning template established in phases 1-6.

**IMPORTANT:** Do NOT use the GSD (get-shit-done) XML template format.

## Custom Skills

- `/my:plan-phase [phase]` — Create phase plans (NOT `/gsd:plan-phase`)
- `/my:research-phase [phase] [style?]` — Create research documentation

## Templates

- **Phase Plans:** `.planning/PHASE-PLAN-TEMPLATE.md` — Comprehensive plan structure
- **Research:** `.planning/PHASE-RESEARCH-TEMPLATE.md` — Hybrid implementation/ecosystem research

## Plan Template Structure

Every PLAN.md must include:
- Objective with bulleted success criteria
- Context (Project State, Key Research Findings, Module Structure, Known Constraints)
- Tasks with Rationale, Steps, Verification, Done
- Success Criteria checklist
- Output Artifacts (file structure, database tables, REST endpoints)
- Next Steps
- Notes

## Research Template Structure (Hybrid)

This project supports **two research styles** based on domain complexity:

### Implementation Style
Use for: Standard Drupal patterns, well-documented libraries, clear implementation path

**Structure:**
- Executive Summary
- Numbered technical sections with inline sources
- Implementation Checklist

**Examples:** Phase 4 (Frontend Integration), Phase 6 (Testing/Validation)

### Ecosystem Research Style
Use for: Web3, AI, niche protocols, domains where "how do experts do this" matters

**Structure:**
- Executive Summary with primary recommendation
- Standard Stack table
- Architecture Patterns with code examples
- Don't Hand-Roll table
- Common Pitfalls
- Sources categorized by confidence level

**Examples:** Phase 2 (WaaP Integration), Phase 7 (ENS Resolution)

### Auto-Detection

The `/my:research-phase` skill auto-detects style based on phase keywords:

| Keywords | Style |
|----------|-------|
| web3, blockchain, ethereum, ens, wallet, siwe, protocol | Ecosystem |
| ai, agent, llm, ml, neural, openai, anthropic | Ecosystem |
| drupal, config, form, entity, crud, test | Implementation |

**Usage:**
```bash
/my:research-phase 08              # Auto-detects style
/my:research-phase 08 ecosystem    # Force ecosystem research
/my:research-phase 08 implementation  # Force implementation style
```

## Why Not GSD Format?

The GSD XML format is optimized for rapid prototyping and context-constrained environments. This project prioritizes:

1. **Comprehensive documentation** for Drupal.org contrib requirements
2. **Team collaboration** with human-readable plans
3. **Production-readiness** with detailed rationale and steps
4. **Two-repo commit workflow** preservation
5. **Hybrid research approach** for both standard and niche domains

The established phases 1-6 template serves these needs better than GSD's terse XML format, while our research template adapts GSD's comprehensive ecosystem research for Web3/AI work.
