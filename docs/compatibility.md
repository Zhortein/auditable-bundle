# Compatibility and deprecation policy

## Supported platform

The future 2.x series targets:

- PHP 8.3 and later within the tested range;
- Symfony 7.4 and 8.x;
- Doctrine ORM 3.x;
- DoctrineBundle 2.19 and 3.x.

Doctrine ORM 2 is not supported. This platform break is the reason for the 2.0 major line.

## Executed compatibility boundaries

The general CI executes six runtime boundaries covering PHP 8.3, 8.4 and 8.5, Symfony 7.4, 8.0 and 8.1, DoctrineBundle 2.19/2.x/3.x, DBAL 3/4 and the lowest dependency boundary without native mbstring.

PostgreSQL 16 is used for the executable shared commit, rollback and fail-closed transactional proof. SQLite is used for most container, mapping and general integration tests. Compatibility is not claimed outside executed matrices and declared Composer constraints.

## Semantic versioning and public API

The project follows Semantic Versioning. The historical 1.0 API is preserved by `tests/Contract/public-api-1.0.0.json`. The complete 2.0 surface is frozen by `tests/Contract/public-api-2.0.0.json`.

Additive public types may be introduced during 2.x. Existing 2.0 types, concrete public classes, interface methods, constructors, enum cases, public properties, finality and readonly semantics are protected for the 2.x series. An incompatible removal or signature change requires a future major version.

The deterministic `doctrine-composite-v1` identifier representation is considered stable during 2.x.

## Deprecation policy

Before removing a supported public surface, the project intends to:

1. document the deprecation;
2. provide and document an alternative where applicable;
3. retain the deprecated path for a reasonable migration period;
4. remove it only in a future major version.

Concrete classes currently represented in the 2.0 public snapshot are protected even when normally obtained through Symfony services. Consumers should still prefer documented interfaces and dependency injection.

See [Upgrade 2.0](../UPGRADE-2.0.md), [Legacy mode](legacy-mode.md) and the [Changelog](../CHANGELOG.md).
