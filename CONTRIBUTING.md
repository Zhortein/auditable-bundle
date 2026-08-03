# Contributing

Thanks for contributing!

## Development

- PHP >= 8.3
- Symfony 7.4 or 8.x
- Doctrine ORM 3
- Composer

## Commands

- Tests: `composer test`
- Static analysis: `composer phpstan`
- Code style: `composer cs:check` / `composer cs:fix`

## Branch workflow

### Ordinary development

Create `feature/*`, `fix/*` or `chore/*` branches from `develop`, then open a draft pull request targeting `develop`. Ordinary changes must never target `main` directly.

Keep commits atomic when their history is useful. Squashing is appropriate for maintenance or Dependabot pull requests with little internal structure. A merge commit is appropriate for larger features whose commits have been deliberately organized.

### Release

Create `release/x.y.z` from `develop`. Finalize the changelog and release-candidate checks on that branch, then open a `release/x.y.z` pull request targeting `main`. Nothing is published before explicit maintainer approval. After publication, synchronize `main` back into `develop`.

### Security fix

Dependabot security updates may target `main`. Apply an urgent security fix to `main` through a pull request, then immediately open a `main` to `develop` synchronization pull request. Also integrate the fix into an active release branch when necessary.

### Prohibited operations

- Do not force-push shared branches.
- Do not move or delete a published tag.
- Do not publish implicitly from a pull request.
