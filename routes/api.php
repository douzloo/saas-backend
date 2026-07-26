<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;


Route::prefix('v1')->group(function () {

    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware([
    	'auth:sanctum',
        'organization'
])->get('/current-organization', function () {

    return response()->json([
        'organization' => app('currentOrganization'),
        'role' => app('currentOrganizationRole'),
        'permissions' => app('currentOrganizationPermissions'),
    ]);

});


});
