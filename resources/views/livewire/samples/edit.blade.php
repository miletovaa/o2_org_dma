<div class="max-w-4xl mx-auto py-8 space-y-6" x-data>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold">Edit Sample</h1>
        <a href="{{ route('samples.index') }}" wire:navigate class="text-sm text-gray-500 hover:underline">Back to samples</a>
    </div>

    @include('livewire.samples._form')

    <div class="bg-white shadow rounded-2xl p-6 space-y-3">
        <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wide">Reference files</h2>
        @if($referenceFiles->isEmpty())
            <p class="text-sm text-gray-500">No reference files attached.</p>
        @else
            <ul class="divide-y divide-gray-100 border rounded-lg">
                @foreach($referenceFiles as $referenceFile)
                    <li class="p-2 text-sm flex items-center justify-between gap-4">
                        <a href="{{ route('reference-files.download', $referenceFile) }}" class="text-indigo-600 hover:underline truncate">{{ $referenceFile->original_name }}</a>
                        <div class="flex items-center gap-3 shrink-0">
                            <span class="text-xs text-gray-500">{{ $referenceFile->humanSize() }}</span>
                            <button
                                type="button"
                                wire:click="detachReferenceFile({{ $referenceFile->id }})"
                                wire:confirm="Detach this file from the sample? The file stays stored and attached to other samples."
                                class="text-xs text-red-600 hover:underline"
                            >
                                Detach
                            </button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        @include('livewire.samples._reference-file-pickers', ['uploadLabel' => 'Add files'])
    </div>

    <div class="flex items-center justify-end gap-3">
        <a href="{{ route('samples.index') }}" wire:navigate class="px-4 py-2 text-sm text-gray-600 hover:underline">Cancel</a>
        <button
            type="button"
            wire:click="save"
            wire:loading.attr="disabled"
            wire:target="save"
            class="bg-black text-white px-5 py-2 rounded-lg hover:opacity-90 disabled:opacity-50"
        >
            <span wire:loading.remove wire:target="save">Save Changes</span>
            <span wire:loading wire:target="save">Saving…</span>
        </button>
    </div>
</div>
