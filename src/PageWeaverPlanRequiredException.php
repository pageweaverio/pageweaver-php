<?php

namespace PageWeaver;

/**
 * `402` — a billing problem, not a credential one: the account's PLAN doesn't include this
 * capability at all (e.g. provenance receipts, proof packs, document versioning, deployments,
 * digital signing, structured e-invoice output, public alias links). No API key, however scoped,
 * can call this successfully until the account upgrades — contrast with
 * {@link PageWeaverPermissionException}, where the feature is available but this specific key isn't
 * allowed to use it.
 */
class PageWeaverPlanRequiredException extends PageWeaverAPIException
{
}
