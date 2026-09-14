@php($appName = __('Loan Assessment'))

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['favicon' => asset('loan-assessment.svg')])
    </head>
    <body class="min-h-screen bg-zinc-50 antialiased dark:bg-zinc-950">
        <main class="flex min-h-svh items-center justify-center p-4 sm:p-6">
            <div class="flex w-full max-w-md flex-col gap-8 rounded-xl border border-zinc-200 bg-white p-6 sm:p-8 dark:border-zinc-800 dark:bg-zinc-900">
                <a href="{{ route('home') }}" class="text-center text-lg font-semibold text-zinc-900 dark:text-white" wire:navigate>
                    {{ $appName }}
                </a>

                {{ $slot }}
            </div>
        </main>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
