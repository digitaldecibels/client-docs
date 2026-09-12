---
description: Create or update CHANGELOG.md from git history
argument-hint: "[--since <tag>] [--release <version>]"
---

Build a changelog a person would want to read, not a reformatted commit log.

Load the `changelog` skill and follow it.

## Dormant check

If `docs/.client-docs.yml` does not exist, say the project is not initialised
and suggest `/docs-init`. This is the one command where continuing anyway is
reasonable if the developer asks, since a changelog needs only git history. Ask
before doing it.

## If CHANGELOG.md already exists

Treat it as hand-written. Find the newest version heading, add only entries
newer than it, match the existing style even where it differs from Keep a
Changelog, and never rewrite or reorder what is there. If it has generated
markers, stay inside them.

## If it does not

Create it in Keep a Changelog format. Read history back to the first tag, or the
whole history if there are no tags, and group into versions by tag.

## Classifying

Use the rules in the `changelog` skill. Leave out formatting commits, internal
refactors, routine dependency bumps, merge commits, work-in-progress and
checkpoint commits, and changes to documentation. Group several commits from one
piece of work into a single entry.

## With --release <version>

Promote `[Unreleased]` to that version, dated today. Never invent a version
number: take it from the argument, a tag, or the project's manifest.

## Report

How many entries were added, under which versions, and whether anything is still
sitting in Unreleased.
