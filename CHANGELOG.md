# Changelog

## 0.4.0

- PSR-4
- Fixed bug in `WHERE` condition building
- Fixed bug where `lastInsertId` would fail in PostgreSQL
- Fixed bug when inserting doubles
- Add `Connection::execCallback` for logging/measuring statements
- Deprecate `Connection::beforeExec`

## 0.3.1

- Fixed bug in invalid limit/offset exception messages

## v0.3.0

- Fixed default param in `Result::fetch`

## v0.2.0

- Revised API with breaking changes

## v0.1.2

- Fixed raw SQL handling

## v0.1.1

- Fixed numeric indexing of `Result::filter`
