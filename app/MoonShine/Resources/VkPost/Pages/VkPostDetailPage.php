<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\VkPost\Pages;

use App\MoonShine\Resources\VkPost\VkPostResource;
use MoonShine\Laravel\Pages\Crud\DetailPage;

/**
 * Fields come from VkPostResource::detailFields().
 * Do not override fields() with a partial list — non-empty page fields
 * replace resource detailFields entirely.
 *
 * @extends DetailPage<VkPostResource>
 */
class VkPostDetailPage extends DetailPage
{
}
