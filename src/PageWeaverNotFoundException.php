<?php

namespace PageWeaver;

/** `404` — no such resource, or it belongs to another tenant (the API never distinguishes the two). */
class PageWeaverNotFoundException extends PageWeaverAPIException
{
}
