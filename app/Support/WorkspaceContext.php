<?php

namespace App\Support;

final class WorkspaceContext
{
    public static function id(): ?int
    {
        $id = session('workspace_id');

        return is_numeric($id) && (int) $id > 0 ? (int) $id : null;
    }
}
