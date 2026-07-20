<?php

declare(strict_types=1);

namespace Workbench\App\Nova;

use Laravel\Nova\Resource as NovaResource;

/**
 * Thin base resource. Intentionally overrides nothing: Nova 4 and Nova 5 declare
 * different signatures for the query hooks, so re-declaring them here would break
 * one major or the other.
 */
abstract class Resource extends NovaResource
{
    //
}
