<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'event_type',
        'subject_type',
        'subject_id',
        'subject_name',
        'details',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): ?\Illuminate\Database\Eloquent\Model
    {
        if ($this->subject_type && $this->subject_id) {
            return $this->subject_type::find($this->subject_id);
        }
        return null;
    }

    // Single source of truth for every event type ActivityLogger can emit — the Activity Log
    // page's filter dropdown and this model's label/color both read from here, so a new event
    // type can't silently go unlabeled or unfilterable again.
    public const EVENTS = [
        'create_project'   => ['label' => 'Created project', 'color' => 'green'],
        'edit_project'     => ['label' => 'Edited project', 'color' => 'blue'],
        'delete_project'   => ['label' => 'Deleted project', 'color' => 'red'],
        'create_compound'  => ['label' => 'Added compound', 'color' => 'green'],
        'edit_compound'    => ['label' => 'Edited compound', 'color' => 'blue'],
        'delete_compound'  => ['label' => 'Deleted compound', 'color' => 'red'],
        'dump_compounds'   => ['label' => 'Exported compounds', 'color' => 'purple'],
        'map_compounds_batch' => ['label' => 'Batch-mapped compounds', 'color' => 'purple'],
        'create_sample'    => ['label' => 'Logged sample', 'color' => 'green'],
        'edit_sample'      => ['label' => 'Edited sample', 'color' => 'blue'],
        'delete_sample'    => ['label' => 'Deleted sample', 'color' => 'red'],
        'import_samples'   => ['label' => 'Imported samples', 'color' => 'purple'],
        'attach_reference_files' => ['label' => 'Attached reference files', 'color' => 'purple'],
        'create_sampling'  => ['label' => 'Logged sampling', 'color' => 'green'],
        'edit_sampling'    => ['label' => 'Edited sampling', 'color' => 'blue'],
        'delete_sampling'  => ['label' => 'Deleted sampling', 'color' => 'red'],
        'create_experiment' => ['label' => 'Created experiment', 'color' => 'green'],
        'delete_experiment' => ['label' => 'Deleted experiment', 'color' => 'red'],
        'create_experiment_record' => ['label' => 'Added experiment record', 'color' => 'green'],
        'edit_experiment_record'   => ['label' => 'Edited experiment record', 'color' => 'blue'],
        'delete_experiment_record' => ['label' => 'Deleted experiment record', 'color' => 'red'],
        'import_elemental_composition' => ['label' => 'Imported elemental composition results', 'color' => 'purple'],
        'edit_global_compound' => ['label' => 'Edited compound', 'color' => 'blue'],
        'create_user' => ['label' => 'Registered', 'color' => 'green'],
        'edit_user'   => ['label' => 'Edited profile', 'color' => 'blue'],
        'change_password' => ['label' => 'Changed password', 'color' => 'blue'],
        'delete_user' => ['label' => 'Deleted account', 'color' => 'red'],
        'login'       => ['label' => 'Logged in', 'color' => 'gray'],
    ];

    public function eventLabel(): string
    {
        return self::EVENTS[$this->event_type]['label'] ?? ucfirst(str_replace('_', ' ', $this->event_type));
    }

    public function eventColor(): string
    {
        return self::EVENTS[$this->event_type]['color'] ?? 'gray';
    }
}
