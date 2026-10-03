<?php

use App\Http\Controllers\ReferenceFileController;
use App\Livewire\ActivityLog\Index as ActivityLogIndex;
use App\Livewire\Compounds\Index as CompoundsIndex;
use App\Livewire\Dashboard\Index as DashboardIndex;
use App\Livewire\ExperimentRecords\Create as ExperimentRecordsCreate;
use App\Livewire\ExperimentRecords\Edit as ExperimentRecordsEdit;
use App\Livewire\ExperimentRecords\ImportElementalComposition;
use App\Livewire\ExperimentRecords\Show as ExperimentRecordsShow;
use App\Livewire\Experiments\Create as ExperimentsCreate;
use App\Livewire\Experiments\Index as ExperimentsIndex;
use App\Livewire\Experiments\Results as ExperimentsResults;
use App\Livewire\Experiments\Show as ExperimentsShow;
use App\Livewire\OptionLists\Index as OptionListsIndex;
use App\Livewire\OptionLists\Show as OptionListsShow;
use App\Livewire\Projects\Index as ProjectsIndex;
use App\Livewire\Projects\Create as ProjectsCreate;
use App\Livewire\Projects\Show as ProjectsShow;
use App\Livewire\Samples\Create as SamplesCreate;
use App\Livewire\Samples\Edit as SamplesEdit;
use App\Livewire\Samples\Import as SamplesImport;
use App\Livewire\Samples\Index as SamplesIndex;
use App\Livewire\Samplings\Create as SamplingsCreate;
use App\Livewire\Samplings\Edit as SamplingsEdit;
use App\Livewire\Samplings\Index as SamplingsIndex;
use App\Livewire\Users\Create as UsersCreate;
use App\Livewire\Users\Edit as UsersEdit;
use App\Livewire\Users\Index as UsersIndex;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardIndex::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::middleware(['auth'])->group(function () {
    Route::get('/compounds', CompoundsIndex::class)->name('compounds.index');

    Route::get('/samplings', SamplingsIndex::class)->name('samplings.index');
    Route::get('/samplings/create', SamplingsCreate::class)->name('samplings.create');
    Route::get('/samplings/{sampling}/edit', SamplingsEdit::class)->name('samplings.edit');

    Route::get('/projects', ProjectsIndex::class)->name('projects.index');
    Route::get('/projects/create', ProjectsCreate::class)->name('projects.create');
    Route::get('/projects/{project}', ProjectsShow::class)->name('projects.show');

    Route::get('/experiments', ExperimentsIndex::class)->name('experiments.index');
    Route::get('/experiments/create', ExperimentsCreate::class)->name('experiments.create');
    Route::get('/experiments/{experiment}', ExperimentsShow::class)->name('experiments.show');
    Route::get('/experiments/{experiment}/results', ExperimentsResults::class)->name('experiments.results');
    Route::get('/experiments/{experiment}/records/create', ExperimentRecordsCreate::class)->name('experiment-records.create');
    Route::get('/experiments/{experiment}/records/import-elemental-composition', ImportElementalComposition::class)->name('experiment-records.import-elemental-composition');
    Route::get('/experiments/{experiment}/records/{record}', ExperimentRecordsShow::class)->name('experiment-records.show');
    Route::get('/experiments/{experiment}/records/{record}/edit', ExperimentRecordsEdit::class)->name('experiment-records.edit');

    Route::get('/samples', SamplesIndex::class)->name('samples.index');
    Route::get('/samples/create', SamplesCreate::class)->name('samples.create');
    Route::get('/samples/import', SamplesImport::class)->name('samples.import');
    Route::get('/samples/{sample}/edit', SamplesEdit::class)->name('samples.edit');
    Route::get('/reference-files/{referenceFile}/download', [ReferenceFileController::class, 'download'])->name('reference-files.download');

    Route::get('/activity-log', ActivityLogIndex::class)->name('activity-log.index');

    Route::get('/option-lists', OptionListsIndex::class)->name('option-lists.index');
    Route::get('/option-lists/{optionList}', OptionListsShow::class)->name('option-lists.show');

    Route::get('/users', UsersIndex::class)->name('users.index');
    Route::get('/users/create', UsersCreate::class)->name('users.create');
    Route::get('/users/{user}/edit', UsersEdit::class)->name('users.edit');
});

require __DIR__.'/auth.php';
