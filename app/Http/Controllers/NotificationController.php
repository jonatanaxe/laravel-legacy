<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * The user's reminder inbox.
 *
 * The database channel stores the notification tied to a notifiable (the
 * user). The query ALWAYS starts from auth()->user()->notifications(): another
 * user's id in the URL is not authorization.
 */
class NotificationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $user = auth()->user();

        return view('notifications.index', [
            'notifications' => $user->notifications()->paginate(15),
            'unread' => $user->unreadNotifications()->count(),
        ]);
    }

    public function read($id)
    {
        // firstOrFail on the user's own query: someone else's id becomes 404,
        // not 403 - it doesn't even confirm that id exists.
        $notification = auth()->user()->notifications()->where('id', $id)->firstOrFail();
        $notification->markAsRead();

        return back()->with('status', 'Reminder marked as read.');
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('status', 'All reminders marked as read.');
    }
}
