<?php

namespace Tests\Http;

/**
 * Reminders: authorized trigger, storage through the database channel, and ownership.
 *
 * What only the edge proves: the notification reaches the owner's inbox,
 * doesn't vanish in an email, and someone else's notification id answers 404
 * (the query always goes through the user's relationship).
 */
class ReminderTest extends HttpTestCase
{
    public function test_owner_triggers_a_reminder()
    {
        $ana = $this->ana();
        $task = $this->personalTaskId();

        $this->assertSame(302, $ana->submit('POST', '/tasks/' . $task . '/remind', [
            '_token' => $ana->token('/tasks/' . $task),
        ])->getStatusCode(), 'owner triggers a reminder');
    }

    public function test_another_user_cannot_trigger_someone_elses_reminder()
    {
        $bruno = $this->bruno();
        $task = $this->personalTaskId();

        $this->assertSame(403, $bruno->submit('POST', '/tasks/' . $task . '/remind', [
            '_token' => $bruno->token('/tasks'),
        ])->getStatusCode(), 'a third party cannot trigger a reminder');
    }

    public function test_inbox_stores_and_marks_as_read()
    {
        $ana = $this->ana();
        $task = $this->personalTaskId();

        $ana->submit('POST', '/tasks/' . $task . '/remind', ['_token' => $ana->token('/tasks/' . $task)]);

        $this->assertSame(200, $ana->status('GET', '/notifications'), 'inbox opens');

        $notification = $ana->firstMatch('/notifications', '#/notifications/([0-9a-f-]{36})/read#');
        $this->assertNotNull($notification, 'the reminder should be stored in the database');

        // The database channel stores the task title; if it vanished in an email,
        // the inbox would be empty.
        $this->assertStringContainsString(
            'policies',
            $ana->body($ana->get('/notifications')),
            'the inbox must show the task title'
        );

        $this->assertSame(404, $this->bruno()->submit('PATCH', '/notifications/' . $notification . '/read', [
            '_token' => $this->bruno()->token('/notifications'),
        ])->getStatusCode(), 'bruno cannot read ana\'s reminder');

        $this->assertSame(302, $ana->submit('PATCH', '/notifications/' . $notification . '/read', [
            '_token' => $ana->token('/notifications'),
        ])->getStatusCode(), 'owner marks as read');
    }
}
