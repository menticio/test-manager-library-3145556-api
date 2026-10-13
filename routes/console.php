<?php

use App\Application\Services\ProvisionAdminService;
use Illuminate\Support\Facades\Artisan;

// T-17 / D-10: startup step that creates the initial admin from the environment. The password is never printed.
Artisan::command('library:provision-admin', function (ProvisionAdminService $service) {
    try {
        $created = $service->provision((string) config('library.admin_username'), (string) config('library.admin_password'));
    } catch (InvalidArgumentException $e) {
        $this->error($e->getMessage());

        return 1;
    }
    $this->info($created ? 'Admin creado desde el entorno.' : 'El admin ya existe; no se modifica.');

    return 0;
})->purpose('Create the initial admin from ADMIN_USERNAME / ADMIN_PASSWORD');
