---
name: schema-diff
description: "Use when the user wants to compare two database schemas, or check a migration before running it. Do not use to run migrations."
---

# schema-diff

List what differs between two schemas, with the risky changes first.

## Steps
1. Get both schemas as SQL text: `pg_dump --schema-only`, `mysqldump --no-data`, or `sqlite3 db.sqlite .schema`. Use read-only credentials.
2. Compare tables, then columns, then indexes and constraints.
3. Report three groups:
   - **Can lose data:** dropped tables or columns, narrower types (`bigint` to `int`, `text` to `varchar(50)`), new NOT NULL without a default.
   - **Changes behavior:** renamed columns, changed defaults, dropped indexes or constraints.
   - **Safe:** new nullable columns, new tables, new indexes.
4. For each risky item give the table, column, old definition and new definition.

## Rules
- Read-only. Never run DDL or a migration.
- If a column looks renamed (one dropped, one added with the same type), say it might be a rename.
