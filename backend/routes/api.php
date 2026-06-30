<?php

use App\Http\Controllers\Api\AirlineController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\CorporateImportController;
use App\Http\Controllers\Api\CustomerContactController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\EmployeeImportController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\NationalityController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RegionScopeController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\MessageTemplateController;
use App\Http\Controllers\Api\TitleController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WhatsAppController;
use App\Http\Controllers\Api\WebsiteSettingController;
use App\Support\Permissions;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);
Route::get('/website-settings', [WebsiteSettingController::class, 'show']);

Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::put('/profile/password', [ProfileController::class, 'updatePassword']);
    Route::get('/dashboard/stats', [DashboardController::class, 'stats'])->middleware('permission:dashboard-view');

    Route::middleware('permission:role-view')->group(function () {
        Route::get('/roles', [RoleController::class, 'index']);
        Route::get('/roles/permissions', [RoleController::class, 'permissions']);
        Route::get('/roles/{role}', [RoleController::class, 'show']);
    });

    Route::post('/roles', [RoleController::class, 'store'])->middleware('permission:role-create');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->middleware('permission:role-update');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:role-delete');

    Route::get('/branches', [BranchController::class, 'index'])
        ->middleware(Permissions::branchReferenceMiddleware());
    Route::get('/branches/{branch}', [BranchController::class, 'show'])
        ->middleware('permission:cabang-view');

    Route::post('/branches', [BranchController::class, 'store'])->middleware('permission:cabang-create');
    Route::put('/branches/{branch}', [BranchController::class, 'update'])->middleware('permission:cabang-update');
    Route::delete('/branches/{branch}', [BranchController::class, 'destroy'])->middleware('permission:cabang-delete');

    Route::get('/nationalities', [NationalityController::class, 'index'])
        ->middleware(Permissions::nationalityReferenceMiddleware());
    Route::get('/nationalities/{nationality}', [NationalityController::class, 'show'])
        ->middleware(Permissions::nationalityReferenceMiddleware());

    Route::post('/nationalities', [NationalityController::class, 'store'])->middleware('permission:kebangsaan-create');
    Route::put('/nationalities/{nationality}', [NationalityController::class, 'update'])->middleware('permission:kebangsaan-update');
    Route::delete('/nationalities/{nationality}', [NationalityController::class, 'destroy'])->middleware('permission:kebangsaan-delete');

    Route::get('/titles', [TitleController::class, 'index'])
        ->middleware(Permissions::titleReferenceMiddleware());
    Route::get('/titles/{title}', [TitleController::class, 'show'])
        ->middleware(Permissions::titleReferenceMiddleware());

    Route::post('/titles', [TitleController::class, 'store'])->middleware('permission:gelar-create');
    Route::put('/titles/{title}', [TitleController::class, 'update'])->middleware('permission:gelar-update');
    Route::delete('/titles/{title}', [TitleController::class, 'destroy'])->middleware('permission:gelar-delete');

    Route::middleware('permission:scope-wilayah-view')->group(function () {
        Route::get('/region-scopes', [RegionScopeController::class, 'index']);
        Route::get('/region-scopes/{regionScope}', [RegionScopeController::class, 'show']);
    });

    Route::post('/region-scopes', [RegionScopeController::class, 'store'])->middleware('permission:scope-wilayah-create');
    Route::put('/region-scopes/{regionScope}', [RegionScopeController::class, 'update'])->middleware('permission:scope-wilayah-update');
    Route::delete('/region-scopes/{regionScope}', [RegionScopeController::class, 'destroy'])->middleware('permission:scope-wilayah-delete');

    Route::middleware('permission:maskapai-view')->group(function () {
        Route::get('/airlines', [AirlineController::class, 'index']);
        Route::get('/airlines/{airline}', [AirlineController::class, 'show']);
    });

    Route::post('/airlines', [AirlineController::class, 'store'])->middleware('permission:maskapai-create');
    Route::put('/airlines/{airline}', [AirlineController::class, 'update'])->middleware('permission:maskapai-update');
    Route::delete('/airlines/{airline}', [AirlineController::class, 'destroy'])->middleware('permission:maskapai-delete');

    Route::middleware('permission:user-view')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/{user}', [UserController::class, 'show']);
    });

    Route::post('/users', [UserController::class, 'store'])->middleware('permission:user-create');
    Route::put('/users/{user}', [UserController::class, 'update'])->middleware('permission:user-update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('permission:user-delete');

    Route::middleware('permission:corporate-view')->group(function () {
        Route::get('/corporate-import/reference', [CorporateImportController::class, 'reference']);
        Route::get('/corporate-import/template', [CorporateImportController::class, 'template']);
        Route::get('/customers', [CustomerController::class, 'index']);
        Route::get('/customers/trashed/list', [CustomerController::class, 'trashed']);
        Route::get('/customers/{customer}', [CustomerController::class, 'show']);
        Route::get('/customers/{customer}/contacts', [CustomerContactController::class, 'index']);
    });

    Route::middleware('permission:pegawai-view')->group(function () {
        Route::get('/employee-import/reference', [EmployeeImportController::class, 'reference']);
        Route::get('/employee-import/template', [EmployeeImportController::class, 'template']);
        Route::get('/employees', [EmployeeController::class, 'index']);
        Route::get('/employees/{employee}', [EmployeeController::class, 'show']);
        Route::get('/customers/{customer}/employees', [EmployeeController::class, 'byCustomer']);
    });

    Route::post('/corporate-import', [CorporateImportController::class, 'import'])
        ->middleware('role_or_permission:import-corporate|import-service');
    Route::post('/employee-import', [EmployeeImportController::class, 'import'])
        ->middleware('permission:import-pegawai');

    Route::middleware('permission:pegawai-create')->group(function () {
        Route::post('/employees', [EmployeeController::class, 'store']);
    });

    Route::middleware('permission:pegawai-update')->group(function () {
        Route::put('/employees/{employee}', [EmployeeController::class, 'update']);
    });

    Route::middleware('permission:pegawai-delete')->group(function () {
        Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy']);
    });

    Route::middleware('permission:corporate-update')->group(function () {
        Route::post('/customers/{customer}/contacts', [CustomerContactController::class, 'store']);
        Route::put('/customers/{customer}/contacts/{contact}', [CustomerContactController::class, 'update']);
    });

    Route::middleware('permission:corporate-delete')->group(function () {
        Route::post('/customers/bulk-delete', [CustomerController::class, 'bulkDestroy']);
        Route::post('/customers/bulk-restore', [CustomerController::class, 'bulkRestore']);
        Route::post('/customers/bulk-force-delete', [CustomerController::class, 'bulkForceDestroy']);
        Route::post('/customers/{id}/restore', [CustomerController::class, 'restore']);
        Route::delete('/customers/{id}/force', [CustomerController::class, 'forceDestroy']);
        Route::delete('/customers/{customer}', [CustomerController::class, 'destroy']);
        Route::delete('/customers/{customer}/contacts/{contact}', [CustomerContactController::class, 'destroy']);
    });

    Route::middleware('permission:template-pesan-view')->group(function () {
        Route::get('/message-templates', [MessageTemplateController::class, 'index']);
        Route::get('/message-templates/placeholders', [MessageTemplateController::class, 'placeholders']);
        Route::get('/message-templates/{messageTemplate}', [MessageTemplateController::class, 'show']);
        Route::get('/whatsapp/status', [WhatsAppController::class, 'status']);
    });

    Route::middleware('permission:template-pesan-create')->group(function () {
        Route::post('/message-templates', [MessageTemplateController::class, 'store']);
    });

    Route::middleware('permission:template-pesan-update')->group(function () {
        Route::put('/message-templates/{messageTemplate}', [MessageTemplateController::class, 'update']);
    });

    Route::middleware('permission:template-pesan-delete')->group(function () {
        Route::delete('/message-templates/{messageTemplate}', [MessageTemplateController::class, 'destroy']);
    });

    Route::middleware('permission:whatsapp-kirim')->group(function () {
        Route::post('/whatsapp/preview', [WhatsAppController::class, 'preview']);
        Route::post('/whatsapp/send', [WhatsAppController::class, 'send']);
    });

    Route::middleware('permission:setting-website-update')->group(function () {
        Route::put('/website-settings', [WebsiteSettingController::class, 'update']);
        Route::post('/website-settings', [WebsiteSettingController::class, 'update']);
    });
});
