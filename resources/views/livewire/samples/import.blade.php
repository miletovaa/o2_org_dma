<div class="max-w-4xl mx-auto py-8 space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold">Import Samples</h1>
        <a href="{{ route('samples.index') }}" wire:navigate class="text-sm text-gray-500 hover:underline">Back to samples</a>
    </div>

    <div class="bg-white shadow rounded-2xl p-6 space-y-4">
        <p class="text-sm text-gray-600">
            Upload a CSV or Excel file with one sample per row. Column headers must match the sample fields
            (<code class="text-xs bg-gray-100 px-1 py-0.5 rounded">sample_group</code>,
            <code class="text-xs bg-gray-100 px-1 py-0.5 rounded">date_received</code>, …) — see
            <code class="text-xs bg-gray-100 px-1 py-0.5 rounded">samples_template.xlsx</code> for the expected
            columns. For fields with multiple values (e.g. <code class="text-xs bg-gray-100 px-1 py-0.5 rounded">feed</code>,
            <code class="text-xs bg-gray-100 px-1 py-0.5 rounded">purpose_of_analysis</code>), separate values with a
            comma (<code class="text-xs bg-gray-100 px-1 py-0.5 rounded">,</code>), e.g. <code class="text-xs bg-gray-100 px-1 py-0.5 rounded">forage, concentrates</code>.
            Rows with an error are skipped and reported below — every other row still imports.
        </p>

        <form wire:submit="import" class="space-y-4">
            <div>
                <input type="file" wire:model="file" accept=".csv,.txt,.xlsx,.xls" class="text-sm">
                @error('file') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                <div wire:loading wire:target="file" class="text-xs text-gray-500 mt-1">Uploading…</div>
            </div>

            <button
                type="submit"
                wire:loading.attr="disabled"
                wire:target="import"
                class="bg-black text-white px-5 py-2 rounded-lg text-sm hover:opacity-90 disabled:opacity-50"
            >
                <span wire:loading.remove wire:target="import">Import</span>
                <span wire:loading wire:target="import">Importing…</span>
            </button>
        </form>
    </div>

    @if($total !== null)
        <div class="bg-white shadow rounded-2xl p-6 space-y-4">
            <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wide">Result</h2>

            @php $pendingDuplicates = collect($duplicates)->where('status', 'pending')->count(); @endphp

            <p class="text-sm {{ $imported > 0 ? 'text-green-700' : 'text-gray-600' }}">
                Imported {{ $imported }} of {{ $total }} row(s).
                @if($pendingDuplicates > 0)
                    {{ $pendingDuplicates }} duplicate{{ $pendingDuplicates === 1 ? '' : 's' }} awaiting your decision below.
                @endif
            </p>

            <div class="border rounded-lg p-4 space-y-4">
                <div>
                    <h3 class="text-xs font-semibold text-gray-900 uppercase tracking-wide">Reference files</h3>
                    <p class="text-xs text-gray-500 mt-1">
                        Files added here are attached to all {{ count($batchSampleIds) }} sample(s) imported in this batch
                        (duplicates you accept or override later get them too).
                    </p>
                </div>

                @if(count($batchSampleIds) === 0)
                    <p class="text-xs text-amber-700">
                        No samples were imported yet. Files you add now are attached as soon as you accept or override a duplicate below.
                    </p>
                @endif

                @if($batchFiles->isNotEmpty())
                    <ul class="divide-y divide-gray-100 border rounded-lg">
                        @foreach($batchFiles as $batchFile)
                            <li class="p-2 text-sm flex items-center justify-between gap-4">
                                <a href="{{ route('reference-files.download', $batchFile) }}" class="text-indigo-600 hover:underline truncate">{{ $batchFile->original_name }}</a>
                                <div class="flex items-center gap-3 shrink-0">
                                    <span class="text-xs text-gray-500">{{ $batchFile->humanSize() }}</span>
                                    <button
                                        type="button"
                                        wire:click="detachFromBatch({{ $batchFile->id }})"
                                        wire:confirm="Remove this file from all samples in this batch?"
                                        class="text-xs text-red-600 hover:underline"
                                    >
                                        Remove
                                    </button>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @include('livewire.samples._reference-file-pickers', ['uploadLabel' => 'Add files to batch'])
            </div>

            @if(count($duplicates) > 0)
                <div>
                    <h3 class="text-xs font-semibold text-amber-700 uppercase tracking-wide mb-2">
                        {{ count($duplicates) }} possible duplicate(s)
                    </h3>
                    <p class="text-xs text-gray-500 mb-2">
                        These rows share a lab sample ID or external ID with an existing sample. Accept to import as a new sample anyway, override to replace the existing sample's data with this row, or decline to skip.
                    </p>
                    <div class="divide-y divide-gray-100 border rounded-lg overflow-hidden">
                        @foreach($duplicates as $index => $duplicate)
                            <div class="p-3 text-sm flex items-center justify-between gap-4">
                                <div>
                                    <span class="font-medium text-gray-900">Row {{ $duplicate['row'] }}:</span>
                                    <span class="text-gray-600">
                                        matches existing sample
                                        <a href="{{ route('samples.edit', $duplicate['existing_id']) }}" target="_blank" class="text-indigo-600 hover:underline">
                                            {{ $duplicate['existing_label'] }}
                                        </a>
                                    </span>
                                </div>

                                @if($duplicate['status'] === 'pending')
                                    <div class="flex items-center gap-2 shrink-0">
                                        <button
                                            type="button"
                                            wire:click="acceptDuplicate({{ $index }})"
                                            class="px-3 py-1 rounded-lg text-xs bg-green-600 text-white hover:opacity-90"
                                        >
                                            Accept
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="overrideDuplicate({{ $index }})"
                                            wire:confirm="Replace the existing sample's data with this row? This cannot be undone."
                                            class="px-3 py-1 rounded-lg text-xs bg-amber-600 text-white hover:opacity-90"
                                        >
                                            Override
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="declineDuplicate({{ $index }})"
                                            class="px-3 py-1 rounded-lg text-xs border border-gray-300 text-gray-700 hover:bg-gray-50"
                                        >
                                            Decline
                                        </button>
                                    </div>
                                @elseif($duplicate['status'] === 'accepted')
                                    <span class="text-xs font-medium text-green-700 shrink-0">Accepted — imported</span>
                                @elseif($duplicate['status'] === 'overridden')
                                    <span class="text-xs font-medium text-amber-700 shrink-0">Overridden — existing sample updated</span>
                                @else
                                    <span class="text-xs font-medium text-gray-500 shrink-0">Declined — skipped</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if(count($rowErrors) > 0)
                <div>
                    <h3 class="text-xs font-semibold text-red-700 uppercase tracking-wide mb-2">
                        {{ count($rowErrors) }} row(s) with errors
                    </h3>
                    <div class="divide-y divide-gray-100 border rounded-lg overflow-hidden">
                        @foreach($rowErrors as $error)
                            <div class="p-3 text-sm">
                                <span class="font-medium text-gray-900">Row {{ $error['row'] }}:</span>
                                <ul class="list-disc list-inside text-red-700 mt-1">
                                    @foreach($error['messages'] as $message)
                                        <li>{{ $message }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>