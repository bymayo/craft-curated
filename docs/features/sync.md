# Sync

Curated fields populate themselves, so you don't normally need to sync anything. Sync exists to **save the current displayed order in one go** — useful after a batch import or when migrating content between environments, so the order is written rather than left implicit.

It's safe to re-run: nothing gets duplicated.

## Sync utility

In the control panel, go to **Utilities → Curated Sync** and click **Sync now**.

## Console command

```sh
php craft curated/sync
```

Same as the utility, just from the terminal. Handy after a deploy, or from a script.

> Note: Curated writes content, not Project Config. The curated order lives in the `curated_relations` table and won't sync between environments through project config — running Sync is one way to materialise the order on each environment. See [Large lists & limits](../guides/large-lists.md#warnings).
