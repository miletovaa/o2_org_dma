<?php

namespace Tests\Feature;

use App\Livewire\Samples\Edit as SamplesEdit;
use App\Livewire\Samples\Import;
use App\Models\ReferenceFile;
use App\Models\Sample;
use App\Models\User;
use Database\Seeders\OptionListSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SampleImportReferenceFilesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(OptionListSeeder::class);
        Storage::fake(ReferenceFile::DISK);
    }

    private function importCsv(User $user, string $csv)
    {
        $file = UploadedFile::fake()->createWithContent('samples.csv', $csv);

        return Livewire::actingAs($user)
            ->test(Import::class)
            ->set('file', $file)
            ->call('import');
    }

    public function test_uploaded_reference_file_is_attached_to_every_sample_in_the_batch(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $outside = Sample::create(['sample_group' => 'plant', 'lab_sample_id' => 'OUTSIDE']);

        $component = $this->importCsv($user, "lab_sample_id,sample_group\nA1,plant\nA2,plant\n");

        $component
            ->set('referenceUploads', [UploadedFile::fake()->create('protocol.pdf', 20, 'application/pdf')])
            ->call('attachUploads')
            ->assertHasNoErrors();

        $file = ReferenceFile::sole();
        Storage::disk(ReferenceFile::DISK)->assertExists($file->path);
        $this->assertEqualsCanonicalizing(['A1', 'A2'], $file->samples->pluck('lab_sample_id')->all());
        $this->assertCount(0, $outside->referenceFiles);
    }

    public function test_already_stored_file_can_be_attached_and_late_duplicates_get_batch_files(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        Sample::create(['sample_group' => 'plant', 'lab_sample_id' => 'DUP']);
        $stored = ReferenceFile::create(['original_name' => 'certificate.pdf', 'path' => 'reference-files/x.pdf', 'size' => 10]);

        $component = $this->importCsv($user, "lab_sample_id,sample_group\nB1,plant\nDUP,plant\n");

        $component
            ->set('existingFileIds', [$stored->id])
            ->call('attachExisting')
            ->assertHasNoErrors()
            ->call('acceptDuplicate', 0);

        $this->assertEqualsCanonicalizing(['B1', 'DUP'], $stored->samples()->pluck('lab_sample_id')->all());
        $this->assertSame(2, $stored->samples()->count());

        $component->call('detachFromBatch', $stored->id);
        $this->assertSame(0, $stored->samples()->count());
    }

    public function test_files_can_be_added_when_every_row_is_a_duplicate(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        Sample::create(['sample_group' => 'plant', 'lab_sample_id' => 'DUP']);

        $component = $this->importCsv($user, "lab_sample_id,sample_group\nDUP,plant\n")
            ->assertSee('Add files to batch')
            ->set('referenceUploads', [UploadedFile::fake()->create('protocol.pdf', 20, 'application/pdf')])
            ->call('attachUploads')
            ->assertHasNoErrors();

        $file = ReferenceFile::sole();
        $this->assertSame(0, $file->samples()->count());

        $component->call('overrideDuplicate', 0);
        $this->assertSame(['DUP'], $file->samples()->pluck('lab_sample_id')->all());
    }

    public function test_files_can_be_uploaded_and_attached_to_a_single_sample(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $sample = Sample::create(['sample_group' => 'plant', 'lab_sample_id' => 'S1']);
        $other = Sample::create(['sample_group' => 'plant', 'lab_sample_id' => 'S2']);
        $stored = ReferenceFile::create(['original_name' => 'certificate.pdf', 'path' => 'reference-files/x.pdf', 'size' => 10]);

        Livewire::actingAs($user)
            ->test(SamplesEdit::class, ['sample' => $sample])
            ->assertSee('certificate.pdf')
            ->set('referenceUploads', [UploadedFile::fake()->create('notes.txt', 1, 'text/plain')])
            ->call('attachUploads')
            ->assertHasNoErrors()
            ->set('existingFileIds', [$stored->id])
            ->call('attachExisting')
            ->assertHasNoErrors()
            ->call('detachReferenceFile', $stored->id);

        $this->assertSame(['notes.txt'], $sample->referenceFiles()->pluck('original_name')->all());
        $this->assertCount(0, $other->referenceFiles);
        $this->assertDatabaseHas('reference_files', ['id' => $stored->id]);
    }

    public function test_download_is_limited_to_users_who_can_see_the_file(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $sample = Sample::create(['sample_group' => 'plant', 'responsible_analyst_id' => $owner->id]);

        Storage::disk(ReferenceFile::DISK)->put('reference-files/doc.txt', 'hello');
        $file = ReferenceFile::create(['original_name' => 'doc.txt', 'path' => 'reference-files/doc.txt', 'size' => 5]);
        $file->samples()->attach($sample);

        $this->actingAs($owner)->get(route('reference-files.download', $file))->assertOk();
        $this->actingAs($stranger)->get(route('reference-files.download', $file))->assertNotFound();
    }
}
