## Definition of Done

A feature is considered complete when:

### ✅ Test-Driven Development
- [ ] **TDD followed strictly** - test written first, fails, then implementation makes it pass
- [ ] **Unit tests added** in `tests/FrenchGuidelinesCheckerTest.php`
- [ ] **Both detection and fixing tested** - error detection AND `--fix` flag behavior
- [ ] **Edge cases covered** - multiple occurrences, already correct text, false positives

### ✅ Core Implementation
- [ ] **Feature works as specified** for all typography rules or translation scenarios
- [ ] **Logic added to correct method** (`processString()` for typography, `glossaryCheck()` for terminology)
- [ ] **Error messages in French** following existing patterns: `"Message describing issue: $text"`
- [ ] **Uses existing constants** (`self::NBSP`, `DOUBLE_PUNCTUATION`, `ELLIPSIS`) or adds new ones if needed

### ✅ Code Quality
- [ ] **All quality checks pass** - `composer qa` (includes cs, stan, test)
- [ ] **PSR-12 code style** - `composer cs` passes
- [ ] **PHPStan level 9** - `composer stan` passes with no errors
- [ ] **No breaking changes** to existing functionality
- [ ] **Follows existing patterns** in the codebase

### ✅ Documentation
- [ ] **Rule marked as implemented** - ✅ checked in `docs/french-rules.md`
- [ ] **README updated if needed** - for major features or new capabilities


### ✅ Manual Testing
- [ ] **CLI tool tested** - `bin/check-translation test-file.po` works correctly
- [ ] **Error detection works** - warnings/errors show properly
- [ ] **Fix functionality works** - `--fix` flag applies corrections
- [ ] **No regressions** - existing rules still work as expected

### ✅ Release Ready
- [ ] **All tests pass** - `composer test` (28+ tests)
- [ ] **No PHPStan errors** at level 9
- [ ] **Code style compliant** with PSR-12
- [ ] **Ready for production use** in translation workflows

The goal: ship typography rules and translation features that work reliably, and can be maintained easily.
