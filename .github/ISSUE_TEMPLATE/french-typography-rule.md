---
name: French Typography Rule Implementation
about: Implement a specific French typography rule
title: "Implement French typography rule: [RULE_NAME]"
labels: enhancement, typography, french-rules, good first issue
assignees: ''
---

## Rule Description

**Rule**: [Describe the rule in plain French]
**Examples**: 
- ❌ Incorrect: `[bad example]`
- ✅ Correct: `[good example]`

**Reference**: `docs/french-rules.md` line X (currently unchecked ❌)

## Implementation Requirements

### 1. Test-Driven Development (TDD)
**MANDATORY**: Write failing test first, then implement
- Add test in `tests/FrenchGuidelinesCheckerTest.php`
- Test both error detection and fixing
- Run `composer test` to verify

### 2. Core Implementation  
- Add logic to `processString()` method in `src/FrenchGuidelinesChecker.php`
- Use existing constants: `self::NBSP`, `DOUBLE_PUNCTUATION`, `ELLIPSIS`, and add constants if needed
- Follow error message pattern: `"Message describing issue: $text"`

### 3. Documentation Updates
- Mark rule as ✅ implemented in `docs/french-rules.md`
- Update README if needed

## Quality Checklist

**All must pass before PR submission:**
- [ ] TDD followed (test written first, fails, then passes)
- [ ] All QA checks: `composer run-script qa`
- [ ] Rule marked as ✅ in `docs/french-rules.md`
- [ ] Manual CLI testing: `bin/check-translation test-file.po`

## Acceptance Criteria
- [ ] Test case added and passes
- [ ] Error detection works correctly
- [ ] Text fixing works with `--fix` flag
- [ ] No regressions in existing functionality
- [ ] Documentation properly updated
- [ ] All quality checks pass

## Resources
- Check existing rules in `processString()` method for implementation patterns

@claude, can you fix this issue with simple implementation, nothing more, nothing less?