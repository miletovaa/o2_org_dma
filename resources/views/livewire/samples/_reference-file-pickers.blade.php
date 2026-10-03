{{-- Upload-new + attach-stored reference file controls. The including Livewire component must expose
     $referenceUploads, $existingFileIds, $existingFileSearch, attachUploads() and attachExisting(),
     and pass $storedFiles to the view. --}}
<div class="grid gap-4 md:grid-cols-2">
    <form wire:submit="attachUploads" class="space-y-2">
        <label class="block text-xs font-medium text-gray-700">Upload new files</label>
        <input type="file" wire:model="referenceUploads" multiple class="text-sm w-full">
        @error('referenceUploads') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
        @error('referenceUploads.*') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
        <div wire:loading wire:target="referenceUploads" class="text-xs text-gray-500">Uploading…</div>
        <button
            type="submit"
            wire:loading.attr="disabled"
            wire:target="referenceUploads,attachUploads"
            class="bg-black text-white px-4 py-1.5 rounded-lg text-xs hover:opacity-90 disabled:opacity-50"
        >
            {{ $uploadLabel }}
        </button>
    </form>

    <form wire:submit="attachExisting" class="space-y-2">
        <label class="block text-xs font-medium text-gray-700">Attach already stored files</label>
        <input
            type="text"
            wire:model.live.debounce.300ms="existingFileSearch"
            placeholder="Search stored files…"
            class="w-full border-gray-300 rounded-lg text-sm px-2 py-1"
        >
        <div class="max-h-40 overflow-y-auto border rounded-lg divide-y divide-gray-100">
            @forelse($storedFiles as $storedFile)
                <label class="flex items-center gap-2 p-2 text-sm cursor-pointer hover:bg-gray-50">
                    <input type="checkbox" wire:model="existingFileIds" value="{{ $storedFile->id }}" class="rounded border-gray-300">
                    <span class="truncate flex-1">{{ $storedFile->original_name }}</span>
                    <span class="text-xs text-gray-400 shrink-0">{{ $storedFile->samples_count }} sample(s)</span>
                </label>
            @empty
                <p class="p-2 text-xs text-gray-500">No stored files found.</p>
            @endforelse
        </div>
        @error('existingFileIds') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
        <button
            type="submit"
            wire:loading.attr="disabled"
            wire:target="attachExisting"
            class="bg-black text-white px-4 py-1.5 rounded-lg text-xs hover:opacity-90 disabled:opacity-50"
        >
            Attach selected
        </button>
    </form>
</div>
