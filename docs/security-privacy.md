# Security and privacy responsibilities

Audit records can concentrate sensitive information. This bundle supplies recording mechanisms; it does not make an application legally compliant or determine which data it may retain. Establish retention and deletion obligations with the application's legal, privacy or compliance advisers.

## Sensitive data

Do not audit passwords, authentication or refresh tokens, API secrets, private keys, temporary codes, complete payment data, or medical and other sensitive data without a documented need and appropriate protection.

## Data-minimization mechanisms

- Use `#[AuditIgnore]` for fields excluded from automatic legacy change detection.
- Use `fields.global_ignored` for property names excluded across audited entities.
- Control every value placed in transactional `data` arrays.
- Control actor metadata produced by custom resolvers or explicit `AuditActor` values.
- Use application-owned factories and storage to validate and minimize transactional records.

These mechanisms depend on application configuration and review; they are not automatic classification of sensitive data.

## Actor identifiers

The default transactional resolver uses `getUserIdentifier()`, which may return an email address or another personal identifier. Replace `AuditActorResolverInterface` when an opaque identifier is more appropriate.

The supplied resolver does not implicitly collect roles, IP addresses, user agents, tenants or organizations. Applications adding such metadata remain responsible for necessity, transparency and retention.

## Storage, access and retention

Restrict access to audit tables and administrative views. Define retention, archival and deletion policies. Apply backup controls and encryption appropriate to the application and its infrastructure. An audit trail may be more sensitive than an individual business record because it aggregates actions, identities and historical values.

Avoid exposing audit payloads or sensitive identifiers in exception messages and logs. Review custom factories, storage implementations and log handlers accordingly.

## Guarantees not supplied

The bundle does not automatically provide:

- encryption at rest;
- cryptographic signatures or hash chaining;
- append-only storage or database immutability;
- tamper evidence;
- archival, purge or anonymization workflows;
- GDPR or sector-specific compliance;
- authorization for an administration interface.

## Transactional mode

Fail-closed recording protects the application-controlled transaction boundary; it is not a cryptographic integrity guarantee. Factory and storage implementations remain responsible for validation and minimization. Using another entity manager or connection removes the demonstrated shared-atomicity guarantee.

## Legacy mode

Legacy mode is fail-open: `Historizer` logs and absorbs failures. Automatic field change detection can capture unexpected values unless `#[AuditIgnore]` and `fields.global_ignored` are applied carefully. Its persister flushes and does not provide the strict recorder's application-controlled shared transaction.

For behavioral details, see [Legacy mode](legacy-mode.md) and [Transactional Doctrine integration](transactional-doctrine.md).
