<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    // The case presentation always lives at "/": it is what gets shown to
    // the CTO. Logged-in users see it too (the hero CTA points to the app)
    // instead of being pushed into the app and never finding it again.
    return view('welcome');
});

// Migration overview: public, like the home page. Shows no user data, only
// the environment, the composer.lock inventory and the suite snapshot.
Route::get('migration', 'MigrationController@index')->name('migration.index');

Auth::routes();

// Trash and restore. `tasks/trash` MUST come before the resource: otherwise
// `/tasks/trash` matches `/tasks/{task}` and tries to open a task "trash".
Route::get('tasks/trash', 'TaskController@trash')->name('tasks.trash')->middleware('auth');
Route::patch('tasks/{id}/restore', 'TaskController@restore')->name('tasks.restore')->middleware('auth');
Route::delete('tasks/{id}/force', 'TaskController@forceDelete')->name('tasks.forceDelete')->middleware('auth');

// App routes: resource controller + policy => CRUD with an owner.
// The controller's middleware would cover a missing 'auth' here; both are
// kept on purpose, as defense in depth.
Route::resource('tasks', 'TaskController')->middleware('auth');

// Flow: the process view (CFD, lead time, WIP aging). Not a Kanban - you
// don't drag cards, you read the whole system over time.
Route::get('flow', 'FlowController@index')->name('flow.index')->middleware('auth');

// Attachments: download is an authenticated route, never a direct disk link.
Route::post('tasks/{task}/attachments', 'TaskAttachmentController@store')
    ->name('tasks.attachments.store')->middleware('auth');
Route::get('attachments/{attachment}/download', 'TaskAttachmentController@download')
    ->name('attachments.download')->middleware('auth');
Route::delete('attachments/{attachment}', 'TaskAttachmentController@destroy')
    ->name('attachments.destroy')->middleware('auth');

// Reminders: on demand, plus the user's notification inbox.
Route::post('tasks/{task}/remind', 'TaskController@remind')
    ->name('tasks.remind')->middleware('auth');
Route::get('notifications', 'NotificationController@index')
    ->name('notifications.index')->middleware('auth');
Route::patch('notifications/{id}/read', 'NotificationController@read')
    ->name('notifications.read')->middleware('auth');
Route::post('notifications/read-all', 'NotificationController@readAll')
    ->name('notifications.readAll')->middleware('auth');

// CSV import: upload in the request, processing on the queue.
Route::get('imports', 'TaskImportController@index')
    ->name('imports.index')->middleware('auth');
Route::post('imports', 'TaskImportController@store')
    ->name('imports.store')->middleware('auth');
Route::get('imports/{id}', 'TaskImportController@show')
    ->name('imports.show')->middleware('auth');
Route::delete('imports/{id}', 'TaskImportController@destroy')
    ->name('imports.destroy')->middleware('auth');

// Projects: membership is managed by the owner. Member routes come before
// the resource so they don't depend on matching order.
Route::post('projects/{project}/members', 'ProjectController@addMember')
    ->name('projects.members.store')->middleware('auth');
Route::delete('projects/{project}/members/{userId}', 'ProjectController@removeMember')
    ->name('projects.members.destroy')->middleware('auth');
Route::resource('projects', 'ProjectController')->middleware('auth');

// Account: user data and API access (token). Generating a new token is a
// POST with CSRF - it invalidates the previous one immediately.
Route::get('account', 'AccountController@show')->name('account.show')->middleware('auth');
Route::post('account/token', 'AccountController@regenerateToken')
    ->name('account.token')->middleware('auth', 'throttle:5,1');

// Billing: screen of the legacy SIG module (app/Legacy). Read only.
Route::get('billing', 'BillingController@index')->name('billing.index')->middleware('auth');

// Project schedule (Gantt-style view over time).
Route::get('projects/{project}/timeline', 'ProjectController@timeline')
    ->name('projects.timeline')
    ->middleware('auth');

// /home is make:auth's post-login destination; here it leads to the dashboard.
Route::get('/home', function () {
    return redirect()->route('dashboard');
})->name('home');

// Dashboard: the first screen after login.
Route::get('dashboard', 'DashboardController@index')->name('dashboard')->middleware('auth');
