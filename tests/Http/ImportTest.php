<?php

namespace Tests\Http;

/**
 * CSV import at the edge: upload, the sync queue used here, per-line report.
 *
 * The test creates its own file with one valid and one invalid line, so
 * the line number in the report is predictable (Line 3).
 */
class ImportTest extends HttpTestCase
{
    public function test_valid_csv_imports_and_report_points_to_the_bad_line()
    {
        $ana = $this->ana();
        $title = 'imported-' . uniqid();

        $csv = $this->tempFile(
            "title,description,priority,done\n"
            . $title . ",valid line,high,\n"
            . "ab,,,\n"
        );

        try {
            $this->assertSame(302, $this->upload($ana, '/imports', '/imports', $csv, 'import.csv'), 'csv upload');
        } finally {
            $this->removeFile($csv);
        }

        $import = $this->newestImport($ana);
        $this->assertGreaterThan(0, $import, 'the report should show up in the list');

        $report = '/imports/' . $import;
        $this->assertSame(200, $ana->status('GET', $report), 'the report opens');
        $this->assertStringContainsString('Line 3', $ana->body($ana->get($report)), 'invalid line listed');

        // The good line became a real task, with the owner coming from the session.
        $payload = $this->apiJson('/api/tasks?q=' . rawurlencode($title), $this->anaToken());
        $this->assertCount(1, $payload['data'], 'the valid line should become a task');
        $this->assertSame($title, $payload['data'][0]['title']);

        $this->assertSame(404, $this->bruno()->status('GET', $report), 'the report belongs to its owner, not the team');

        $this->cleanupTask((int) $payload['data'][0]['id']);
        $this->assertSame(302, $ana->submit('DELETE', $report, ['_token' => $ana->token('/imports')])
            ->getStatusCode(), 'the report can be removed');
    }

    /**
     * Highest /imports/{id} in the list: the report just created, without
     * depending on the view's ordering.
     */
    private function newestImport(Browser $browser)
    {
        $ids = [];

        foreach ($browser->links('/imports') as $href) {
            if (preg_match('#/imports/(\d+)#', $href, $match)) {
                $ids[] = (int) $match[1];
            }
        }

        return $ids ? max($ids) : 0;
    }

    /**
     * Cleanup: the import creates a real task in the database. It leaves through
     * the trash and the force delete, which is the path the product offers.
     */
    private function cleanupTask($task)
    {
        $ana = $this->ana();
        $token = $ana->token('/tasks');

        $ana->submit('DELETE', '/tasks/' . $task, ['_token' => $token]);
        $ana->submit('DELETE', '/tasks/' . $task . '/force', ['_token' => $token]);
    }
}
