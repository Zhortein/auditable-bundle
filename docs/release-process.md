# Release process

This process is for project maintainers. Creating release branches, merging to `main`, tagging, creating GitHub Releases and publishing to Packagist remain manual operations that require explicit maintainer authorization.

## Branch model

The release progression is:

```text
feature/* -> develop
develop -> release/x.y.z
release/x.y.z -> main
tag x.y.z
GitHub Release
Packagist
main -> develop
```

Tags use the exact `x.y.z` form without a `v` prefix.

## Before creating a release branch

- Confirm that the integration pull request is green.
- Confirm that no unreviewed change is included.
- Confirm that the public API snapshots pass.
- Confirm that the general compatibility matrix passes.
- Confirm that the PostgreSQL checks pass.
- Confirm that PHPStan passes at maximum level.
- Confirm that Composer Audit passes.
- Confirm that the package archive check passes.
- Confirm that migration documentation is complete.

## Creating release/x.y.z

Create `release/x.y.z` from `develop`. Do not force-push it. Change the version, changelog and release date only as part of the release work. Explicitly review the Composer branch alias for the branch being prepared; the subsequent synchronization of `main` and `develop` may require a different alias decision for the continuing development cycle. Do not publish anything at this stage.

## Pull request to main

Open a pull request with `main` as its base and `release/x.y.z` as its head. A merge commit is recommended to preserve the release topology. All required checks must pass. Do not enable automatic merging, and do not create the tag before the pull request is merged with explicit maintainer approval.

## Tag and publication

Only after explicit maintainer authorization:

1. Create the exact `x.y.z` tag on the validated `main` commit.
2. Never move that tag.
3. Create the GitHub Release for the tag.
4. Verify the release on Packagist.

Never rebuild or replace a release using the same version number.

## Synchronize main back to develop

Open a pull request from `main` to `develop` and use a merge commit. Resolve every divergence explicitly. Do not cherry-pick a partial collection of release commits. Verify that `develop` contains the expected tag and release state.

## Dependabot

GitHub reads `.github/dependabot.yml` from the default branch, `main`. Version updates target `develop` through `target-branch`. Security updates target the default branch, `main`. After applying a security fix on `main`, immediately synchronize `main` back to `develop`.

## Rollback and recovery

Never delete or rewrite a published tag. Revert a release with a new commit or prepare a new corrective version. A ruleset may be temporarily disabled only during documented administrator recovery. Never force-push to rewrite a public release.
