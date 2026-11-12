<?php

namespace App\Http\Controllers;

use App\Migration\Overview;

/**
 * Migration overview - the entry point of the case.
 *
 * Public on purpose: it is the first thing shown, and a visitor without a
 * session still needs to see where the legacy stands. Nothing here is user
 * data; only environment, inventory and suite results.
 */
class MigrationController extends Controller
{
    public function index()
    {
        return view('migration.index', [
            'overview' => (new Overview())->build(),
        ]);
    }
}
