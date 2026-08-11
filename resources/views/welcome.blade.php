<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body @class(['font-sans', 'antialiased', 'bg-gray-100', 'text-gray-900'])>
        <div @class(['min-h-screen', 'flex', 'flex-col'])>
            <header @class(['max-w-5xl', 'mx-auto', 'w-full', 'px-6', 'py-6', 'flex', 'items-center', 'justify-between'])>
                <span @class(['font-semibold', 'text-lg', 'tracking-tight'])>{{ config('app.name') }}</span>

                <nav @class(['flex', 'items-center', 'gap-4', 'text-sm'])>
                    @auth
                        <a href="{{ route('tasks.index') }}" @class(['px-4', 'py-2', 'bg-indigo-600', 'text-white', 'rounded-md'])>
                            Minhas tarefas
                        </a>
                    @else
                        <a href="{{ route('login') }}" @class(['text-gray-600', 'hover:text-gray-900'])>Entrar</a>
                        <a href="{{ route('register') }}" @class(['px-4', 'py-2', 'bg-indigo-600', 'text-white', 'rounded-md'])>
                            Criar conta
                        </a>
                    @endauth
                </nav>
            </header>

            <main @class(['flex-1', 'flex', 'items-center'])>
                <div @class(['max-w-5xl', 'mx-auto', 'w-full', 'px-6', 'py-12', 'grid', 'gap-10', 'lg:grid-cols-2', 'lg:items-center'])>
                    <div>

                        <h1 @class(['text-3xl', 'sm:text-4xl', 'font-semibold', 'tracking-tight', 'leading-tight'])>
                            <span @class(['inline-flex', 'items-center', 'justify-center', 'w-15', 'h-15', 'rounded-md', 'bg-indigo-600', 'text-white', 'mb-6'])>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" @class(['w-5', 'h-5'])>
                                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                                </svg>
                            </span>
                            Organize suas tarefas, sem se preocupar com as dos outros.
                        </h1>
                        <p @class(['mt-4', 'text-gray-600', 'leading-relaxed'])>
                            Crie, acompanhe e conclua suas tarefas em um só lugar. Cada conta só enxerga e gerencia
                            as suas próprias.
                        </p>

                        <div @class(['mt-8', 'mb-8', 'flex', 'items-center', 'gap-4'])>
                            @auth
                                <a href="{{ route('tasks.index') }}" @class(['px-5', 'py-2.5', 'bg-indigo-600', 'text-white', 'rounded-md', 'font-medium'])>
                                    Ver minhas tarefas
                                </a>
                            @else
                                <a href="{{ route('register') }}" @class(['px-5', 'py-2.5', 'bg-indigo-600', 'text-white', 'rounded-md', 'font-medium'])>
                                    Criar conta
                                </a>
                                <a href="{{ route('login') }}" @class(['px-5', 'py-2.5', 'text-gray-700', 'font-medium', 'hover:text-gray-900'])>
                                    Já tenho conta
                                </a>
                            @endauth
                        </div>
                    </div>

                    <div @class(['bg-white', 'rounded-lg', 'shadow-sm', 'p-6'])>
                        <p @class(['text-xs', 'font-medium', 'text-gray-400', 'uppercase', 'tracking-wide', 'mb-4'])>Minhas tarefas</p>

                        <ul @class(['space-y-3'])>
                            <li @class(['flex', 'items-center', 'justify-between', 'py-2', 'border-b', 'border-gray-100'])>
                                <span>Revisar proposta do cliente</span>
                                <span @class(['text-xs', 'px-2', 'py-1', 'rounded-full', 'bg-green-100', 'text-green-800'])>concluída</span>
                            </li>
                            <li @class(['flex', 'items-center', 'justify-between', 'py-2', 'border-b', 'border-gray-100'])>
                                <span>Preparar apresentação</span>
                                <span @class(['text-xs', 'px-2', 'py-1', 'rounded-full', 'bg-yellow-100', 'text-yellow-800'])>em andamento</span>
                            </li>
                            <li @class(['flex', 'items-center', 'justify-between', 'py-2'])>
                                <span>Responder e-mails pendentes</span>
                                <span @class(['text-xs', 'px-2', 'py-1', 'rounded-full', 'bg-gray-100', 'text-gray-600'])>pendente</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </main>
        </div>
    </body>
</html>
