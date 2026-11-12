<?php

namespace Tests\Http;

/**
 * Attachments at the edge: what the server accepts, where it stores, who downloads.
 *
 * Feature PHPUnit proves the policy in the kernel; this proves the real
 * multipart (content-type, size, extension) and the 403 from another session.
 */
class AttachmentTest extends HttpTestCase
{
    public function test_valid_upload_and_authorized_download()
    {
        $ana = $this->ana();
        $task = $this->personalTaskId();
        $show = '/tasks/' . $task;

        $file = $this->tempFile("verification report\n");

        try {
            $this->assertSame(302, $this->upload($ana, $show . '/attachments', $show, $file, 'note.txt'), 'txt upload');
        } finally {
            $this->removeFile($file);
        }

        $attachment = $ana->firstId($show, '#/attachments/(\d+)/download#');
        $this->assertGreaterThan(0, $attachment, 'the attachment should show up on the task');

        $download = '/attachments/' . $attachment . '/download';
        $this->assertSame(200, $ana->status('GET', $download), 'owner downloads');
        $this->assertSame(403, $this->bruno()->status('GET', $download), 'a third party cannot download');
        $this->assertSame(302, $this->guest()->status('GET', $download), 'a guest cannot download');

        $this->assertSame(302, $ana->submit('DELETE', '/attachments/' . $attachment, [
            '_token' => $ana->token($show),
        ])->getStatusCode(), 'owner removes');

        $this->assertSame(404, $ana->status('GET', $download), 'a removed attachment cannot be downloaded');
    }

    public function test_disguised_php_is_rejected()
    {
        $ana = $this->ana();
        $task = $this->personalTaskId();
        $show = '/tasks/' . $task;

        $file = $this->tempFile('<?php echo "pwned";');

        try {
            // Validation rejects it by type; it comes back 302 with a form error.
            $this->assertSame(302, $this->upload($ana, $show . '/attachments', $show, $file, 'shell.php'), 'php must be rejected');
        } finally {
            $this->removeFile($file);
        }

        $html = $ana->body($ana->get($show));
        $this->assertStringNotContainsString('shell.php', $html, 'a rejected file must not become an attachment');
    }

    public function test_upload_without_token_is_stopped_by_csrf()
    {
        $task = $this->personalTaskId();
        $file = $this->tempFile('no token');

        try {
            $response = $this->guest()->request('POST', '/tasks/' . $task . '/attachments', [
                'multipart' => [
                    ['name' => 'file', 'contents' => fopen($file, 'r'), 'filename' => 'nota.txt'],
                ],
            ]);

            $this->assertSame(419, $response->getStatusCode(), 'an upload without _token must be 419');
        } finally {
            $this->removeFile($file);
        }
    }
}
