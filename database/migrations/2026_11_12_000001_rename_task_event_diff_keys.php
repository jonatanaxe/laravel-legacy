<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Converts the diff keys of old audit events to English.
 *
 * Events recorded before the English rename stored each change as
 * {"de": ..., "para": ...}; new ones use {"from": ..., "to": ...}. Rewriting
 * the old rows lets TaskEvent read a single shape.
 *
 * Idempotent: only rows that still have an old key are touched, in chunks so
 * a large history doesn't load at once. The old keys below are the data being
 * converted, so they have to appear here as they are stored.
 *
 * down() is a no-op on purpose: after up() the converted rows can no longer
 * be told apart from native ones, and every reader understands from/to.
 */
class RenameTaskEventDiffKeys extends Migration
{
    const KEYS = ['de' => 'from', 'para' => 'to'];

    public function up()
    {
        DB::table('task_events')
            ->where(function ($query) {
                foreach (array_keys(self::KEYS) as $old) {
                    $query->orWhere('meta', 'like', '%"' . $old . '"%');
                }
            })
            ->orderBy('id')
            ->chunkById(200, function ($events) {
                foreach ($events as $event) {
                    $meta = json_decode($event->meta, true);

                    if (! is_array($meta) || ! isset($meta['changed']) || ! is_array($meta['changed'])) {
                        continue;
                    }

                    foreach ($meta['changed'] as $field => $diff) {
                        $meta['changed'][$field] = $this->renameKeys($diff);
                    }

                    DB::table('task_events')
                        ->where('id', $event->id)
                        ->update(['meta' => json_encode($meta)]);
                }
            });
    }

    public function down()
    {
        // Intentionally empty: see the class comment.
    }

    private function renameKeys($diff)
    {
        if (! is_array($diff)) {
            return $diff;
        }

        foreach (self::KEYS as $old => $new) {
            if (array_key_exists($old, $diff)) {
                if (! array_key_exists($new, $diff)) {
                    $diff[$new] = $diff[$old];
                }

                unset($diff[$old]);
            }
        }

        return $diff;
    }
}
