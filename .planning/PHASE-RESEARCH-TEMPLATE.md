# Phase Research Template

This project supports two research styles depending on domain complexity.

## Style Selection

| Use **Implementation Style** when... | Use **Ecosystem Research Style** when... |
|-------------------------------------|------------------------------------------|
| Standard Drupal patterns | Niche/complex domains (AI, Web3, 3D, audio) |
| Well-documented libraries | Claude's training data is sparse/outdated |
| Clear implementation path | Need "how do experts do this" research |
| Focused on specific decisions | Need ecosystem landscape, pitfalls, SOTA |

**Examples:**
- Implementation: Config forms, REST endpoints, standard CRUD
- Ecosystem: ENS resolution, AI agents, Web3 protocols, novel integrations

---

## Style 1: Implementation Research (Standard Drupal)

Use for well-known tech where you need implementation decisions.

```markdown
# Phase X Research: [Topic]

**Date:** YYYY-MM-DD
**Status:** [In Progress/Complete]
**Phase:** [Phase name]

---

## Executive Summary

[Key findings and recommendations - 2-3 paragraphs]

---

## Section 1: [Topic]

[Technical content with decisions]

**Sources:** [URL]

---

## Section 2: [Topic]

[Technical content]

**Sources:** [URL]

---

## Implementation Checklist

- [ ] [Action item 1]
- [ ] [Action item 2]

---

## Sources

1. [Source with URL]
2. [Source with URL]
```

**Reference:** Phase 4 (Frontend Integration) or Phase 6 (Testing/Validation)

---

## Style 2: Ecosystem Research (Niche/Complex Domains)

Use for Web3, AI, or domains where "how do experts do this" matters.

```markdown
# Phase X Research: [Topic]

**Researched:** YYYY-MM-DD
**Domain:** [primary technology/problem domain]
**Confidence:** [HIGH/MEDIUM/LOW]
**Phase:** [Phase name]

---

## Executive Summary

[2-3 paragraph executive summary]
- What was researched
- What the standard approach is
- Key recommendations

**Primary recommendation:** [one-liner actionable guidance]

---

## Standard Stack

The established libraries/tools for this domain:

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| [name] | [ver] | [what it does] | [why experts use it] |

**Installation:**
```bash
composer require [package]
# or
npm install [package]
```

---

## Architecture Patterns

### Pattern 1: [Pattern Name]
**What:** [description]
**When to use:** [conditions]
**Example:**
```php
// [code example from official docs]
```

### Anti-Patterns to Avoid
- **[Anti-pattern]:** [why it's bad, what to do instead]

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| [problem] | [what you'd build] | [library] | [edge cases, complexity] |

---

## Common Pitfalls

### Pitfall 1: [Name]
**What goes wrong:** [description]
**Why it happens:** [root cause]
**How to avoid:** [prevention strategy]
**Warning signs:** [how to detect early]

---

## Sources

### Primary (HIGH confidence)
- [Official docs URL] - [what was checked]
- [Context7 library ID] - [topics fetched]

### Secondary (MEDIUM confidence)
- [WebSearch verified with official source] - [finding]

### Tertiary (LOW confidence - needs validation)
- [WebSearch only] - [finding, marked for validation]
```

**Reference:** Phase 7 (ENS Resolution), Phase 2 (WaaP Integration)

---

## Decision Guide for Research Style

When invoking `/my:research-phase`, the skill will auto-detect appropriate style based on:

1. **Phase name/keywords** - "Web3", "AI", "ENS", "protocol" → Ecosystem style
2. **Your preference** - Can specify style via argument

**Usage:**
```bash
/my:research-phase 08              # Auto-detects style
/my:research-phase 08 ecosystem    # Force ecosystem research
/my:research-phase 08 implementation  # Force implementation style
```
