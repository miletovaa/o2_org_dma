<?php

namespace App\Livewire\Samples;

use App\Models\OptionList;
use App\Models\Project;
use App\Models\ReferenceFile;
use App\Models\Sample;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

class Edit extends Component
{
    use WithFileUploads;

    public Sample $sample;

    public string $labSampleId = '';
    public string $externalId = '';
    public string $matrixName = '';
    public string $group = '';
    public string $subgroup = '';
    public string $dateReceived = '';
    public string $storageCondition = '';
    public array $storageConditionDetails = [];
    public ?int $responsibleAnalystId = null;
    public ?int $projectId = null;
    public string $newProjectName = '';
    public string $selectedProjectName = '';
    public array $purposeOfAnalysis = [];
    public array $plannedAnalysis = [];
    public array $typeDetails = [];
    public string $note = '';

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile[] */
    public array $referenceUploads = [];
    public array $existingFileIds = [];
    public string $existingFileSearch = '';

    public function mount(Sample $sample): void
    {
        abort_unless(Sample::visibleTo(Auth::user(), 'edit')->whereKey($sample->id)->exists(), 404);

        $this->sample = $sample;

        $this->labSampleId = (string) $sample->lab_sample_id;
        $this->externalId = (string) $sample->external_id;
        $this->matrixName = (string) $sample->matrix_name;
        $this->group = (string) $sample->sample_group;
        $this->subgroup = (string) $sample->sample_subgroup;
        $this->dateReceived = $sample->date_received?->format('Y-m-d') ?? '';
        $this->storageCondition = (string) $sample->storage_condition;
        $this->storageConditionDetails = $sample->storage_condition_details ?? [];
        $this->responsibleAnalystId = $sample->responsible_analyst_id;
        $this->projectId = $sample->project_id;
        $this->selectedProjectName = $sample->project?->name ?? '';
        $this->purposeOfAnalysis = $sample->purpose_of_analysis ?? [];
        $this->plannedAnalysis = $sample->planned_analysis ?? [];
        $this->typeDetails = $sample->type_details ?? [];
        $this->note = (string) $sample->note;
    }

    /** Upload new reference files and attach them to this sample. */
    public function attachUploads(): void
    {
        $this->validate([
            'referenceUploads' => ['required', 'array', 'min:1'],
            // 12 MB matches Livewire's default temporary-upload limit — larger files never reach us.
            'referenceUploads.*' => ['file', 'max:12288'],
        ], [], ['referenceUploads' => 'files', 'referenceUploads.*' => 'file']);

        $files = collect($this->referenceUploads)->map(fn ($upload) => ReferenceFile::storeUpload($upload));

        $this->attachFiles($files);
        $this->referenceUploads = [];
    }

    /** Attach files already stored in the system to this sample. */
    public function attachExisting(): void
    {
        $this->validate([
            'existingFileIds' => ['required', 'array', 'min:1'],
        ], ['existingFileIds.required' => 'Select at least one stored file.']);

        $files = ReferenceFile::visibleTo(Auth::user())->whereKey($this->existingFileIds)->get();

        $this->attachFiles($files);
        $this->existingFileIds = [];
    }

    private function attachFiles($files): void
    {
        if ($files->isEmpty()) {
            return;
        }

        $this->sample->referenceFiles()->syncWithoutDetaching($files->pluck('id')->all());
        ActivityLogger::attachReferenceFiles($files->pluck('original_name')->all(), 1);

        $this->resetValidation(['referenceUploads', 'referenceUploads.*', 'existingFileIds']);
    }

    public function detachReferenceFile(int $fileId): void
    {
        $this->sample->referenceFiles()->detach($fileId);
    }

    public function updatedGroup(): void
    {
        // A subgroup can apply to more than one group, but not every group — reset it so a
        // stale selection that doesn't fit the new group can't slip through.
        $this->subgroup = '';
        $this->typeDetails = [];
    }

    public function save(): void
    {
        $this->validate($this->rules());

        $project = $this->resolveProject();

        $oldProjectName = $this->sample->project?->name ?? '—';

        $this->sample->fill([
            'lab_sample_id' => $this->labSampleId ?: null,
            'external_id' => $this->externalId ?: null,
            'matrix_name' => $this->matrixName ?: null,
            'sample_group' => $this->group,
            'sample_subgroup' => $this->subgroup ?: null,
            'date_received' => $this->dateReceived ?: null,
            'storage_condition' => $this->storageCondition ?: null,
            'storage_condition_details' => $this->storageConditionDetails ?: null,
            'responsible_analyst_id' => $this->responsibleAnalystId,
            'project_id' => $project?->id,
            'purpose_of_analysis' => $this->purposeOfAnalysis ?: null,
            'planned_analysis' => $this->plannedAnalysis ?: null,
            'type_details' => $this->hasTypeDetails() ? (array_filter($this->typeDetails, fn ($v) => $v !== '' && $v !== null && $v !== []) ?: null) : null,
            'note' => $this->note ?: null,
        ]);

        $changes = ActivityLogger::diff($this->sample);
        if ($this->sample->isDirty('project_id')) {
            $changes['project'] = $oldProjectName . ' → ' . ($project?->name ?? '—');
            unset($changes['project_id']);
        }

        $this->sample->save();

        ActivityLogger::editSample($this->sample, $changes);

        session()->flash('success', 'Sample updated.');

        $this->redirect(route('samples.index'), navigate: true);
    }

    protected function hasTypeDetails(): bool
    {
        return in_array($this->group, Sample::TYPE_DETAIL_GROUPS, true);
    }

    protected function resolveProject(): ?Project
    {
        if (trim($this->newProjectName) !== '') {
            return Project::firstOrCreate(
                ['name' => trim($this->newProjectName)],
                ['status' => 'active', 'user_id' => Auth::id()],
            );
        }

        if ($this->projectId) {
            return Project::visibleTo(Auth::user())->find($this->projectId);
        }

        return null;
    }

    protected function rules(): array
    {
        return [
            'group' => ['required', 'in:' . implode(',', array_keys(OptionList::optionsFor('sample_groups')))],
            'subgroup' => ['nullable', 'in:' . implode(',', array_keys(OptionList::subOptionsFor('sample_subgroups', $this->group)))],
            'dateReceived' => ['nullable', 'date'],
            'storageCondition' => ['nullable', 'in:' . implode(',', array_keys(OptionList::optionsFor('storage_conditions')))],
            'responsibleAnalystId' => ['nullable', 'exists:users,id'],
            'projectId' => ['nullable', 'exists:projects,id'],
        ];
    }

    public function render()
    {
        return view('livewire.samples.edit', array_merge(
            [
                'users' => User::orderBy('name')->get(['id', 'name']),
                'projects' => Project::visibleTo(Auth::user())->orderBy('name')->get(['id', 'name']),
                'referenceFiles' => $referenceFiles = $this->sample->referenceFiles()->orderBy('original_name')->get(),
                'storedFiles' => $this->storedFiles($referenceFiles->pluck('id')->all()),
            ],
            $this->optionLists(),
        ))->layout('layouts.app');
    }

    protected function storedFiles(array $exceptIds)
    {
        $search = trim($this->existingFileSearch);

        return ReferenceFile::visibleTo(Auth::user())
            ->whereNotIn('id', $exceptIds)
            ->when($search !== '', fn ($q) => $q->whereRaw('LOWER(original_name) LIKE ?', ['%' . mb_strtolower($search) . '%']))
            ->withCount('samples')
            ->latest()
            ->limit(50)
            ->get();
    }

    protected function optionLists(): array
    {
        return [
            'groups' => OptionList::optionsFor('sample_groups'),
            'subgroupOptions' => OptionList::subOptionsFor('sample_subgroups', $this->group),
            'storageConditions' => OptionList::optionsFor('storage_conditions'),
            'storageConditionDetailsOptions' => OptionList::optionsFor('storage_condition_details'),
            'purposesOfAnalysis' => OptionList::optionsFor('purposes_of_analysis'),
            'plannedAnalyses' => OptionList::optionsFor('planned_analyses'),
            'statusOptions' => OptionList::optionsFor('status_options'),
            'productionTypes' => OptionList::optionsFor('production_types'),
            'sourceOfWaterOptions' => OptionList::optionsFor('source_of_water'),
            'partOfPlantOptions' => OptionList::optionsFor('part_of_plant'),
            'plantProducerOptions' => OptionList::optionsFor('plant_producer'),
            'animalProducerOptions' => OptionList::optionsFor('animal_producer'),
            'plantProcessingTypes' => OptionList::optionsFor('plant_processing_types'),
            'partOfAnimalOptions' => OptionList::optionsFor('part_of_animal'),
            'animalProcessingTypes' => OptionList::optionsFor('animal_processing_types'),
            'animalFeedTypes' => OptionList::optionsFor('animal_feed_types'),
        ];
    }
}
