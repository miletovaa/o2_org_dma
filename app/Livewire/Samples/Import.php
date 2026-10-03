<?php

namespace App\Livewire\Samples;

use App\Imports\SamplesImport;
use App\Models\ReferenceFile;
use App\Models\Sample;
use App\Services\ActivityLogger;
use App\Services\SampleImporter;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

class Import extends Component
{
    use WithFileUploads;

    public $file = null;

    public ?int $total = null;
    public ?int $imported = null;
    public array $rowErrors = [];

    /** @var array<int, array{row: int, attributes: array, existing_id: int, existing_label: string, status: string}> */
    public array $duplicates = [];

    /** IDs of every sample created or updated by this import batch (incl. accepted/overridden duplicates). */
    public array $batchSampleIds = [];

    /** Reference files attached to this batch — re-applied to duplicates resolved after attaching. */
    public array $batchFileIds = [];

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile[] */
    public array $referenceUploads = [];

    public array $existingFileIds = [];
    public string $existingFileSearch = '';

    protected function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:10240'],
        ];
    }

    public function import(): void
    {
        $this->validate();

        $sheet = new SamplesImport();
        Excel::import($sheet, $this->file->getRealPath());

        $result = (new SampleImporter())->import($sheet->rows ?? collect());

        $this->batchSampleIds = $result['samples']->pluck('id')->all();
        $this->batchFileIds = [];
        $this->referenceUploads = [];
        $this->existingFileIds = [];

        $this->total = $result['total'];
        $this->imported = $result['imported'];
        $this->rowErrors = $result['errors'];
        $this->duplicates = collect($result['duplicates'])->map(fn ($d) => [
            'row' => $d['row'],
            'attributes' => $d['attributes'],
            'existing_id' => $d['existing']->id,
            'existing_label' => $d['existing']->lab_sample_id ?: ($d['existing']->external_id ?: ('#' . $d['existing']->id)),
            'status' => 'pending',
        ])->all();

        if ($result['imported'] > 0) {
            ActivityLogger::importSamples($result['imported'], $result['total'], $this->file->getClientOriginalName());
        }

        $this->file = null;
        $this->resetValidation();
    }

    public function acceptDuplicate(int $index): void
    {
        if (! isset($this->duplicates[$index]) || $this->duplicates[$index]['status'] !== 'pending') {
            return;
        }

        $sample = Sample::create($this->duplicates[$index]['attributes']);
        ActivityLogger::createSample($sample);
        $this->addToBatch($sample);

        $this->duplicates[$index]['status'] = 'accepted';
        $this->imported++;
    }

    public function declineDuplicate(int $index): void
    {
        if (! isset($this->duplicates[$index]) || $this->duplicates[$index]['status'] !== 'pending') {
            return;
        }

        $this->duplicates[$index]['status'] = 'declined';
    }

    public function overrideDuplicate(int $index): void
    {
        if (! isset($this->duplicates[$index]) || $this->duplicates[$index]['status'] !== 'pending') {
            return;
        }

        $sample = Sample::findOrFail($this->duplicates[$index]['existing_id']);
        $sample->fill($this->duplicates[$index]['attributes']);
        $changes = ActivityLogger::diff($sample);
        $sample->save();

        if ($changes) {
            ActivityLogger::editSample($sample, $changes);
        }
        $this->addToBatch($sample);

        $this->duplicates[$index]['status'] = 'overridden';
        $this->imported++;
    }

    /** Upload new reference files and attach them to every sample in the batch. */
    public function attachUploads(): void
    {
        $this->validate([
            'referenceUploads' => ['required', 'array', 'min:1'],
            // 12 MB matches Livewire's default temporary-upload limit — larger files never reach us.
            'referenceUploads.*' => ['file', 'max:12288'],
        ], [], ['referenceUploads' => 'files', 'referenceUploads.*' => 'file']);

        $files = collect($this->referenceUploads)->map(fn ($upload) => ReferenceFile::storeUpload($upload));

        $this->attachToBatch($files);
        $this->referenceUploads = [];
    }

    /** Attach files already stored in the system to every sample in the batch. */
    public function attachExisting(): void
    {
        $this->validate([
            'existingFileIds' => ['required', 'array', 'min:1'],
        ], ['existingFileIds.required' => 'Select at least one stored file.']);

        $files = ReferenceFile::visibleTo(Auth::user())->whereKey($this->existingFileIds)->get();

        $this->attachToBatch($files);
        $this->existingFileIds = [];
    }

    public function detachFromBatch(int $fileId): void
    {
        if (! in_array($fileId, $this->batchFileIds, true)) {
            return;
        }

        ReferenceFile::find($fileId)?->samples()->detach($this->batchSampleIds);
        $this->batchFileIds = array_values(array_diff($this->batchFileIds, [$fileId]));
    }

    private function attachToBatch($files): void
    {
        if ($files->isEmpty()) {
            return;
        }

        foreach ($files as $file) {
            $file->samples()->syncWithoutDetaching($this->batchSampleIds);
        }

        $this->batchFileIds = array_values(array_unique(array_merge($this->batchFileIds, $files->pluck('id')->all())));

        if ($this->batchSampleIds) {
            ActivityLogger::attachReferenceFiles($files->pluck('original_name')->all(), count($this->batchSampleIds));
        }

        $this->resetValidation();
    }

    /** A duplicate accepted/overridden after files were attached still gets the batch's files. */
    private function addToBatch(Sample $sample): void
    {
        if (! in_array($sample->id, $this->batchSampleIds, true)) {
            $this->batchSampleIds[] = $sample->id;
        }

        if ($this->batchFileIds) {
            $sample->referenceFiles()->syncWithoutDetaching($this->batchFileIds);
        }
    }

    public function render()
    {
        $storedFiles = collect();
        $batchFiles = collect();

        if ($this->total !== null) {
            $search = trim($this->existingFileSearch);

            $storedFiles = ReferenceFile::visibleTo(Auth::user())
                ->whereNotIn('id', $this->batchFileIds)
                ->when($search !== '', fn ($q) => $q->whereRaw('LOWER(original_name) LIKE ?', ['%' . mb_strtolower($search) . '%']))
                ->withCount('samples')
                ->latest()
                ->limit(50)
                ->get();

            $batchFiles = ReferenceFile::whereKey($this->batchFileIds)->orderBy('original_name')->get();
        }

        return view('livewire.samples.import', [
            'storedFiles' => $storedFiles,
            'batchFiles' => $batchFiles,
        ])->layout('layouts.app');
    }
}