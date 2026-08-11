<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Minhas tarefas</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 p-4 bg-green-100 text-green-800 rounded">{{ session('status') }}</div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="flex justify-between items-center mb-4">
                    <form method="GET" action="{{ route('tasks.index') }}">
                        <select name="status" onchange="this.form.submit()" class="border-gray-300 rounded-md">
                            <option value="">Todos os status</option>
                            @foreach ($statuses as $status)
                                <option value="{{ $status->value }}" @selected($selectedStatus?->value === $status->value)>
                                    {{ $status->label() }}
                                </option>
                            @endforeach
                        </select>
                    </form>

                    <a href="{{ route('tasks.create') }}" class="px-4 py-2 bg-indigo-600 text-white rounded-md">
                        Nova tarefa
                    </a>
                </div>

                <table class="w-full text-left">
                    <thead>
                        <tr>
                            <th class="pb-2">Título</th>
                            <th class="pb-2">Descrição</th>
                            <th class="pb-2">Status</th>
                            <th class="pb-2">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tasks as $task)
                            <tr class="border-t">
                                <td class="py-2">{{ $task->title }}</td>
                                <td class="py-2 max-w-xs truncate">{{ $task->description ?: 'Sem descrição.' }}</td>
                                <td class="py-2">{{ $task->status->label() }}</td>
                                <td class="py-2 space-x-2">
                                    <a href="{{ route('tasks.show', $task) }}">Detalhes</a>
                                    <a href="{{ route('tasks.edit', $task) }}">Editar</a>
                                    <form method="POST" action="{{ route('tasks.destroy', $task) }}" class="inline"
                                        onsubmit="return confirm('Tem certeza que deseja excluir esta tarefa?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600">Excluir</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-4 text-gray-500">Nenhuma tarefa encontrada.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="mt-4">{{ $tasks->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
