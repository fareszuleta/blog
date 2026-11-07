---
name: sql-explain
description: "Use when the user pastes a SQL query and asks why it is slow or how to speed it up. Do not use for schema design questions."
---

# sql-explain

Explain a slow query in plain words and suggest an index.

## Steps
1. Ask which database it is (PostgreSQL, MySQL, SQLite) if you cannot tell.
2. Run the plan, never the query's side effects:
   - PostgreSQL: `EXPLAIN (ANALYZE, BUFFERS) <query>` only for SELECT. For other statements use plain `EXPLAIN`.
   - MySQL: `EXPLAIN ANALYZE <query>`
   - SQLite: `EXPLAIN QUERY PLAN <query>`
3. Find the costly step: a sequential or full table scan on a large table, a sort that spills to disk, a join with no index on the join column, a row estimate far from the real count.
4. Explain it in two or three plain sentences, then propose the index as a `CREATE INDEX` statement and say what it speeds up and what it slows down (writes).

## Rules
- Never run `ANALYZE` on INSERT, UPDATE or DELETE: it executes them.
- Suggest the index; do not create it.
