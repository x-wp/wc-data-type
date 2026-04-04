# Release Process

## Branches

- `beta` is the prerelease branch. Pushes here may publish prerelease builds only.
- `master` is the stable release branch. Stable tags are cut from commits that land here.

## v2.0.0 Trigger

Semantic Release will not infer a major version from test, fix, refactor, or chore commits alone. To cut `v2.0.0`, the merge commit that lands on `master` must include an explicit breaking-change signal:

- Use a conventional commit subject with `!`, such as `feat!: ship stateful data API`.
- Or include a `BREAKING CHANGE:` footer in the commit body that explains the incompatible change.

## Release Checklist

Before merging a stable release to `master`:

1. Run `composer test` and confirm the full suite passes.
2. Confirm the semantic-release dry-run job passes in GitHub Actions.
3. Verify the release artifact contains `src/`, `lib/`, `composer.json`, `README.md`, and `LICENSE`.
4. Summarize the breaking changes and migration notes in the release notes.

## Notes

The GitHub release artifact is intended to be usable outside a Packagist install, so it must include both the runtime source tree and the autoload metadata declared in `composer.json`.
