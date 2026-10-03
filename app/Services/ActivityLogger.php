<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ActivityLogger
{
    public static function log(
        string $eventType,
        ?string $details = null,
        ?Model  $subject = null,
        ?string $subjectName = null,
        array   $metadata = [],
        ?int    $userId = null,
    ): ActivityLog {
        return ActivityLog::create([
            'user_id'      => $userId ?? Auth::id(),
            'event_type'   => $eventType,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id'   => $subject?->getKey(),
            'subject_name' => $subjectName ?? ($subject ? (string) ($subject->name ?? $subject->canonical_name ?? $subject->getKey()) : null),
            'details'      => $details,
            'metadata'     => $metadata ?: null,
        ]);
    }

    /**
     * Diff a model's currently-dirty attributes into human-readable "old → new" strings.
     * Call after fill()ing new attributes but before save(), so getOriginal() still reflects
     * the pre-update values.
     */
    public static function diff(Model $model): array
    {
        return collect($model->getDirty())
            ->reject(fn ($new, $field) => in_array($field, ['updated_at'], true))
            ->mapWithKeys(fn ($new, $field) => [
                $field => (static::stringify($model->getOriginal($field)) ?: '—') . ' → ' . (static::stringify($new) ?: '—'),
            ])
            ->all();
    }

    private static function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_array($value)) {
            return implode(', ', $value);
        }
        return (string) $value;
    }

    // ── Convenience helpers ───────────────────────────────────────────────────

    public static function createProject(Model $project): ActivityLog
    {
        return static::log('create_project', "Created project \"{$project->name}\".", $project, $project->name);
    }

    public static function editProject(Model $project, array $changes = []): ActivityLog
    {
        $detail = "Edited project \"{$project->name}\".";
        if ($changes) {
            $parts = [];
            foreach ($changes as $field => $value) {
                $parts[] = "{$field} → {$value}";
            }
            $detail .= ' Changes: ' . implode('; ', $parts) . '.';
        }
        return static::log('edit_project', $detail, $project, $project->name, $changes);
    }

    public static function deleteProject(string $projectName, ?int $projectId = null): ActivityLog
    {
        return static::log(
            'delete_project',
            "Deleted project \"{$projectName}\".",
            null,
            $projectName,
            $projectId ? ['project_id' => $projectId] : [],
        );
    }

    public static function createCompound(Model $projectCompound, Model $project): ActivityLog
    {
        $name = $projectCompound->custom_name ?? $projectCompound->compound?->canonical_name ?? "#{$projectCompound->id}";
        return static::log(
            'create_compound',
            "Added compound \"{$name}\" to project \"{$project->name}\".",
            $projectCompound,
            $name,
            ['project_id' => $project->id, 'project_name' => $project->name],
        );
    }

    public static function editCompound(Model $projectCompound, Model $project, array $changes = []): ActivityLog
    {
        $name   = $projectCompound->custom_name ?? $projectCompound->compound?->canonical_name ?? "#{$projectCompound->id}";
        $detail = "Edited compound \"{$name}\" in project \"{$project->name}\".";
        if ($changes) {
            $parts = [];
            foreach ($changes as $field => $value) {
                $parts[] = "{$field} → {$value}";
            }
            $detail .= ' Changes: ' . implode('; ', $parts) . '.';
        }
        return static::log(
            'edit_compound',
            $detail,
            $projectCompound,
            $name,
            array_merge(['project_id' => $project->id, 'project_name' => $project->name], $changes),
        );
    }

    public static function deleteCompound(string $compoundName, Model $project): ActivityLog
    {
        return static::log(
            'delete_compound',
            "Deleted compound \"{$compoundName}\" from project \"{$project->name}\".",
            null,
            $compoundName,
            ['project_id' => $project->id, 'project_name' => $project->name],
        );
    }

    public static function dumpCompounds(Model $project, int $count): ActivityLog
    {
        return static::log(
            'dump_compounds',
            "Exported {$count} compound(s) from project \"{$project->name}\".",
            $project,
            $project->name,
            ['project_id' => $project->id, 'compound_count' => $count],
        );
    }

    private static function sampleName(Model $sample): string
    {
        return $sample->lab_sample_id ?: ($sample->external_id ?: "#{$sample->id}");
    }

    public static function createSample(Model $sample): ActivityLog
    {
        $name = static::sampleName($sample);
        return static::log('create_sample', "Logged sample \"{$name}\".", $sample, $name);
    }

    public static function editSample(Model $sample, array $changes = []): ActivityLog
    {
        $name   = static::sampleName($sample);
        $detail = "Edited sample \"{$name}\".";
        if ($changes) {
            $parts = [];
            foreach ($changes as $field => $value) {
                $parts[] = "{$field} → {$value}";
            }
            $detail .= ' Changes: ' . implode('; ', $parts) . '.';
        }
        return static::log('edit_sample', $detail, $sample, $name, $changes);
    }

    public static function importSamples(int $imported, int $total, string $fileName): ActivityLog
    {
        $failed = $total - $imported;
        $detail = "Imported {$imported} of {$total} sample(s) from \"{$fileName}\".";
        if ($failed > 0) {
            $detail .= " {$failed} row(s) failed.";
        }

        return static::log(
            'import_samples',
            $detail,
            null,
            $fileName,
            ['imported' => $imported, 'total' => $total, 'failed' => $failed],
        );
    }

    /** @param string[] $fileNames */
    public static function attachReferenceFiles(array $fileNames, int $sampleCount): ActivityLog
    {
        $list = implode('", "', $fileNames);
        $detail = 'Attached reference file(s) "' . $list . "\" to {$sampleCount} sample(s).";

        return static::log(
            'attach_reference_files',
            $detail,
            null,
            implode(', ', $fileNames),
            ['files' => $fileNames, 'sample_count' => $sampleCount],
        );
    }

    public static function importElementalComposition(Model $experiment, int $importedSamples, int $total, string $fileName): ActivityLog
    {
        $failed = $total - $importedSamples;
        $detail = "Imported elemental composition results for {$importedSamples} of {$total} sample(s) in experiment \"{$experiment->name}\" from \"{$fileName}\".";
        if ($failed > 0) {
            $detail .= " {$failed} row(s) failed.";
        }

        return static::log(
            'import_elemental_composition',
            $detail,
            $experiment,
            $experiment->name,
            ['imported_samples' => $importedSamples, 'total' => $total, 'failed' => $failed, 'file' => $fileName],
        );
    }

    public static function deleteSample(string $sampleName, ?int $sampleId = null): ActivityLog
    {
        return static::log(
            'delete_sample',
            "Deleted sample \"{$sampleName}\".",
            null,
            $sampleName,
            $sampleId ? ['sample_id' => $sampleId] : [],
        );
    }

    private static function samplingName(Model $sampling): string
    {
        $sample = $sampling->sample;
        return $sample?->lab_sample_id ?: ($sample?->external_id ?: "#{$sampling->id}");
    }

    public static function createSampling(Model $sampling): ActivityLog
    {
        $name = static::samplingName($sampling);
        return static::log('create_sampling', "Logged sampling for \"{$name}\".", $sampling, $name);
    }

    public static function editSampling(Model $sampling, array $changes = []): ActivityLog
    {
        $name   = static::samplingName($sampling);
        $detail = "Edited sampling for \"{$name}\".";
        if ($changes) {
            $parts = [];
            foreach ($changes as $field => $value) {
                $parts[] = "{$field} → {$value}";
            }
            $detail .= ' Changes: ' . implode('; ', $parts) . '.';
        }
        return static::log('edit_sampling', $detail, $sampling, $name, $changes);
    }

    public static function deleteSampling(string $sampleName, ?int $samplingId = null): ActivityLog
    {
        return static::log(
            'delete_sampling',
            "Deleted sampling for \"{$sampleName}\".",
            null,
            $sampleName,
            $samplingId ? ['sampling_id' => $samplingId] : [],
        );
    }

    public static function createExperiment(Model $experiment): ActivityLog
    {
        return static::log('create_experiment', "Created experiment \"{$experiment->name}\".", $experiment, $experiment->name);
    }

    public static function deleteExperiment(string $experimentName, ?int $experimentId = null): ActivityLog
    {
        return static::log(
            'delete_experiment',
            "Deleted experiment \"{$experimentName}\".",
            null,
            $experimentName,
            $experimentId ? ['experiment_id' => $experimentId] : [],
        );
    }

    public static function createExperimentRecord(Model $record): ActivityLog
    {
        $label = $record->recordTypeLabel();
        return static::log(
            'create_experiment_record',
            "Added \"{$label}\" record to experiment \"{$record->experiment?->name}\".",
            $record,
            $label,
            ['experiment_id' => $record->experiment_id],
        );
    }

    public static function deleteExperimentRecord(string $recordLabel, Model $experiment): ActivityLog
    {
        return static::log(
            'delete_experiment_record',
            "Deleted \"{$recordLabel}\" record from experiment \"{$experiment->name}\".",
            null,
            $recordLabel,
            ['experiment_id' => $experiment->id],
        );
    }

    public static function editExperimentRecord(Model $record, array $changes = []): ActivityLog
    {
        $label  = $record->recordTypeLabel();
        $detail = "Edited \"{$label}\" record on experiment \"{$record->experiment?->name}\".";
        if ($changes) {
            $parts = [];
            foreach ($changes as $field => $value) {
                $parts[] = "{$field} → {$value}";
            }
            $detail .= ' Changes: ' . implode('; ', $parts) . '.';
        }
        return static::log(
            'edit_experiment_record',
            $detail,
            $record,
            $label,
            array_merge(['experiment_id' => $record->experiment_id], $changes),
        );
    }

    public static function editGlobalCompound(Model $compound, array $changes = []): ActivityLog
    {
        $name   = $compound->canonical_name ?? "#{$compound->id}";
        $detail = "Edited compound \"{$name}\".";
        if ($changes) {
            $parts = [];
            foreach ($changes as $field => $value) {
                $parts[] = "{$field} → {$value}";
            }
            $detail .= ' Changes: ' . implode('; ', $parts) . '.';
        }
        return static::log('edit_global_compound', $detail, $compound, $name, $changes);
    }

    public static function mapCompoundsBatch(Model $project, array $counts, ?int $userId = null): ActivityLog
    {
        $total      = $counts['total'] ?? 0;
        $localFound = $counts['local_found'] ?? 0;
        $pubchemNew = $counts['pubchem_new'] ?? 0;
        $notFound   = $counts['not_found'] ?? 0;

        return static::log(
            'map_compounds_batch',
            "Batch-mapped {$total} compound(s) in project \"{$project->name}\": {$localFound} found locally, {$pubchemNew} newly resolved, {$notFound} not found.",
            $project,
            $project->name,
            array_merge(['project_id' => $project->id], $counts),
            $userId,
        );
    }

    // ── User account ─────────────────────────────────────────────────────────

    public static function createUser(Model $user): ActivityLog
    {
        return static::log('create_user', "Registered account \"{$user->name}\".", $user, $user->name, [], $user->id);
    }

    public static function editUser(Model $user, array $changes = []): ActivityLog
    {
        $detail = "Edited profile \"{$user->name}\".";
        if ($changes) {
            $parts = [];
            foreach ($changes as $field => $value) {
                $parts[] = "{$field} → {$value}";
            }
            $detail .= ' Changes: ' . implode('; ', $parts) . '.';
        }
        return static::log('edit_user', $detail, $user, $user->name, $changes);
    }

    public static function changePassword(Model $user): ActivityLog
    {
        // Never log the password value itself — event only, no diff.
        return static::log('change_password', "Changed password for \"{$user->name}\".", $user, $user->name);
    }

    public static function deleteUser(string $userName, ?int $userId = null): ActivityLog
    {
        return static::log(
            'delete_user',
            "Deleted account \"{$userName}\".",
            null,
            $userName,
            $userId ? ['user_id' => $userId] : [],
            $userId,
        );
    }

    public static function login(Model $user): ActivityLog
    {
        return static::log('login', "\"{$user->name}\" logged in.", $user, $user->name, [], $user->id);
    }
}
