<?php

namespace PageWeaver;

/**
 * `403` — the API key authenticated fine but is not allowed to do this: either it lacks a required
 * scope ({@link isScopeMissing} true, {@link getRequiredScope} names it — mint a new key with that
 * scope in the portal), or the account can't see this resource for another reason (e.g. an
 * object-type access policy). This is a credential problem, distinct from
 * {@link PageWeaverPlanRequiredException} (a billing problem: the credential is fine, the feature
 * isn't on the plan at all).
 */
class PageWeaverPermissionException extends PageWeaverAPIException
{
    /** The API's stable code for "this API key authenticated fine but lacks a required scope". */
    private const SCOPE_MISSING_CODE = 'authorization.scope_missing';

    /** True when this 403 is specifically a missing-scope refusal (`code === "authorization.scope_missing"`). */
    public function isScopeMissing(): bool
    {
        return $this->code === self::SCOPE_MISSING_CODE;
    }

    /**
     * The scope name the key is missing (e.g. `"review"`), when {@link isScopeMissing} is true and
     * the API's message named it (best-effort parse of "missing the 'X' scope" — returns null rather
     * than guessing). Mint a new API key with that scope in the portal to resolve it.
     */
    public function getRequiredScope(): ?string
    {
        if (!$this->isScopeMissing()) {
            return null;
        }
        if (preg_match("/missing the '([^']+)' scope/", $this->getMessage(), $m) === 1) {
            return $m[1];
        }
        return null;
    }
}
