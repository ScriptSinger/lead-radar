<?php

use App\Http\Controllers\WorkspaceContextController;
use App\Http\Controllers\WorkspaceInvitationController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::get('/workspace-invitations/{token}', [WorkspaceInvitationController::class, 'show'])
    ->name('workspace-invitations.show');
Route::post('/workspace-invitations/{token}', [WorkspaceInvitationController::class, 'accept'])
    ->middleware('throttle:10,1')
    ->name('workspace-invitations.accept');

Route::post('/admin/workspaces/{workspace}/activate', [WorkspaceContextController::class, 'update'])
    ->middleware('auth:moonshine')
    ->name('workspaces.activate');
