<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $task->title }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <p class="mb-2"><strong>Título:</strong> {{ $task->title }}</p>
                <p class="mb-2"><strong>Status:</strong> {{ $task->status->label() }}</p>
                <p class="mb-4"><strong>Descrição:</strong> {{ $task->description ?: 'Sem descrição.' }}</p>
                <a href="{{ route('tasks.edit', $task) }}" class="text-indigo-600">Editar</a>
                <a href="{{ route('tasks.index') }}" class="ml-4 text-gray-600">Voltar</a>
            </div>
        </div>
    </div>
</x-app-layout>
