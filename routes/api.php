<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes  (automatic prefix: /api, middleware group: api)
|--------------------------------------------------------------------------
|
| The "api" group already applies throttle:60,1 (see app/Http/Kernel.php)
| and route binding. Here we only declare the resource.
|
| Authentication: Authorization: Bearer <token>, resolved by the "token"
| guard (config/auth.php) against users.api_token.
|
*/

Route::middleware('auth:api')->group(function () {
    // Who am I - lets a client validate its token with no side effects.
    Route::get('/user', function (Request $request) {
        return response()->json(['data' => $request->user('api')]);
    });

    // Read only: the API proves the domain isn't locked inside the views.
    // Writes stay on the web side, with CSRF - two paths, two auth models.
    Route::get('/tasks', 'Api\TaskController@index')->name('api.tasks.index');
    Route::get('/tasks/{task}', 'Api\TaskController@show')->name('api.tasks.show');
});
