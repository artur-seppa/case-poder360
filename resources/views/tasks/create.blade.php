<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Nova tarefa</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('tasks.store') }}">
                    @csrf
                    @include('tasks._form', ['task' => null])
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md">Criar</button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
