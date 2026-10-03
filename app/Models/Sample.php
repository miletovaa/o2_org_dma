<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Sample extends Model
{
    use HasFactory;

    protected $fillable = [
        'lab_sample_id',
        'external_id',
        'matrix_name',
        'sample_group',
        'sample_subgroup',
        'date_received',
        'storage_condition',
        'storage_condition_details',
        'responsible_analyst_id',
        'project_id',
        'purpose_of_analysis',
        'planned_analysis',
        'type_details',
        'note',
    ];

    /** Everyone can always view/edit/delete samples they're responsible for or that belong
     *  to a project they own; the `samples.$action` permission extends that to every other sample. */
    public function scopeVisibleTo(Builder $query, User $user, string $action = 'view'): Builder
    {
        if ($user->hasPermission('samples', $action)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where('responsible_analyst_id', $user->id)
                ->orWhereHas('project', fn (Builder $p) => $p->where('user_id', $user->id));
        });
    }

    protected $casts = [
        'date_received' => 'date',
        'storage_condition_details' => 'array',
        'purpose_of_analysis' => 'array',
        'planned_analysis' => 'array',
        'type_details' => 'array',
    ];

    // Groups that carry a dedicated sample type details block (identification → type details → storage).
    // Structural — decides which extra fields render, not itself a value list, so it stays a code constant
    // rather than living in the predefined-values admin page.
    public const TYPE_DETAIL_GROUPS = ['plant', 'animal'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function responsibleAnalyst(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_analyst_id');
    }

    public function sampling(): HasOne
    {
        return $this->hasOne(Sampling::class);
    }

    public function referenceFiles(): BelongsToMany
    {
        return $this->belongsToMany(ReferenceFile::class)->withTimestamps();
    }

    /** Subgroups relevant to this sample's group (a subgroup can apply to more than one group — see OptionListSeeder). */
    public function subgroupOptions(): array
    {
        return OptionList::subOptionsFor('sample_subgroups', $this->sample_group);
    }

    public function groupLabel(): ?string
    {
        return OptionList::optionsFor('sample_groups')[$this->sample_group] ?? $this->sample_group;
    }

    public function subgroupLabel(): ?string
    {
        return $this->subgroupOptions()[$this->sample_subgroup] ?? $this->sample_subgroup;
    }

    public function storageConditionLabel(): ?string
    {
        return OptionList::optionsFor('storage_conditions')[$this->storage_condition] ?? $this->storage_condition;
    }
}
