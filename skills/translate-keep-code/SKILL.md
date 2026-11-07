---
name: translate-keep-code
description: "Use when the user asks to translate documentation or Markdown that contains code, commands or file names. Do not use for translating source code comments only."
---

# translate-keep-code

Translate the prose and leave everything technical exactly as written.

## Keep unchanged
- Fenced code blocks and inline `code`.
- Shell commands, file paths, URLs, flags, environment variable names.
- Product, library and API names.
- Front matter keys and anchors that other pages link to.

## Steps
1. Ask for the target language if it was not given.
2. Translate headings, paragraphs, list items and table cells. Keep the same Markdown structure, line for line where possible.
3. Keep code comments untranslated unless the user asks.
4. After translating, diff the code blocks of the original and the translation; they must be identical.

## Rules
- Write the result to a new file (`name.es.md`), not over the original.
- Use the terms the project already uses if a glossary exists.
