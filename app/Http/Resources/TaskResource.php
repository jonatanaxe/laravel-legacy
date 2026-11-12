<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The API's output contract.
 *
 * The view is not the only consumer: the JSON shape is explicit here, so
 * renaming a DB column can't break a client without going through this file.
 * `overdue` is computed on the server - the business rule doesn't leak into
 * the client.
 */
class TaskResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority,
            'done' => (bool) $this->done,
            'overdue' => $this->isOverdue(),
            'due_date' => $this->due_date ? $this->due_date->toDateString() : null,
            // Project included in the contract: without it the consumer can't
            // tell a personal task from a shared one.
            'project' => $this->project ? [
                'id' => $this->project->id,
                'name' => $this->project->name,
            ] : null,
            'created_at' => $this->created_at ? $this->created_at->toAtomString() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toAtomString() : null,
            'links' => [
                'self' => route('api.tasks.show', $this->id),
            ],
        ];
    }
}
