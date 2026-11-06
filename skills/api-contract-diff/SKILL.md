---
name: api-contract-diff
description: "Use when the user compares two versions of an API or OpenAPI spec, or asks whether a change is breaking for clients. Do not use to generate client code."
---

# api-contract-diff

Separate breaking changes from safe ones between two OpenAPI files.

## Steps
1. Get both specs (old and new). If a tool is allowed, use it: `npx openapi-diff old.yaml new.yaml`.
2. Without a tool, compare paths, methods, parameters, request bodies and responses by hand.
3. Mark as **breaking**: a removed path or method, a removed or renamed field in a response, a parameter that became required, a narrower type or enum, a changed status code.
4. Mark as **safe**: a new optional field or parameter, a new path, a wider enum on a response.
5. Output two lists with the path and method for each item.

## Rules
- A new required field in a *request* is breaking; a new field in a *response* is usually safe.
- Say when you could not tell (for example an unchanged schema referenced indirectly).
