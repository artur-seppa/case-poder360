<?php

namespace App\Http\Controllers\Api;

use App\Actions\Task\CreateTask;
use App\Actions\Task\DeleteTask;
use App\Actions\Task\ListTasks;
use App\Actions\Task\ShowTask;
use App\Actions\Task\UpdateTask;
use App\Http\Controllers\Controller;
use App\Http\Requests\Task\IndexTaskRequest;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TaskController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ListTasks $listTasks,
        private readonly CreateTask $createTask,
        private readonly ShowTask $showTask,
        private readonly UpdateTask $updateTask,
        private readonly DeleteTask $deleteTask,
    ) {
    }

    /**
     * Lista as tarefas do usuário autenticado.
     *
     * @group Tarefas
     *
     * @queryParam status string Filtra por status. Valores possíveis: `pendente`, `em_andamento`, `concluida`. Example: pendente
     * @queryParam page integer O número da página a ser exibida. Example: 2
     */
    public function index(IndexTaskRequest $request): AnonymousResourceCollection
    {
        return TaskResource::collection($this->listTasks->handle($request->user(), $request->status()));
    }

    /**
     * Cria uma nova tarefa para o usuário autenticado.
     *
     * @group Tarefas
     *
     * @bodyParam title string required O título da tarefa. Example: Revisar documentação da API
     * @bodyParam description string O detalhamento da tarefa. Example: Adicionar Scribe e cobrir os principais fluxos.
     * @bodyParam status string O status inicial. Valores possíveis: `pendente`, `em_andamento`, `concluida`. Example: pendente
     *
     * @response 422 scenario="Título ausente" {
     *   "message": "Os dados enviados são inválidos.",
     *   "errors": {
     *     "title": ["The title field is required."]
     *   }
     * }
     */
    public function store(StoreTaskRequest $request): JsonResponse
    {
        $task = $this->createTask->handle($request->user(), $request->toDto());

        return TaskResource::make($task)->response()->setStatusCode(201);
    }

    /**
     * Exibe uma tarefa específica.
     *
     * @group Tarefas
     *
     * @response 403 scenario="Tarefa pertence a outro usuário" {
     *   "message": "Você não tem permissão para acessar este recurso."
     * }
     */
    public function show(Task $task): TaskResource
    {
        $this->authorize('view', $task);

        return TaskResource::make($this->showTask->handle($task));
    }

    /**
     * Atualiza uma tarefa existente.
     *
     * @group Tarefas
     *
     * @bodyParam title string O novo título. Example: Revisar documentação da API (v2)
     * @bodyParam description string O novo detalhamento.
     * @bodyParam status string O novo status. Valores possíveis: `pendente`, `em_andamento`, `concluida`. Example: em_andamento
     *
     * @response 403 scenario="Tarefa pertence a outro usuário" {
     *   "message": "Você não tem permissão para acessar este recurso."
     * }
     */
    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        return TaskResource::make($this->updateTask->handle($task, $request->toDto()));
    }

    /**
     * Remove uma tarefa.
     *
     * @group Tarefas
     *
     * @response 403 scenario="Tarefa pertence a outro usuário" {
     *   "message": "Você não tem permissão para acessar este recurso."
     * }
     */
    public function destroy(Task $task): JsonResponse
    {
        $this->authorize('delete', $task);

        $this->deleteTask->handle($task);

        return response()->json(null, 204);
    }
}
