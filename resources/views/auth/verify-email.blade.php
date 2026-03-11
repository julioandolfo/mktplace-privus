<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
        Obrigado por se cadastrar! Antes de comecar, verifique seu e-mail clicando no link que acabamos de enviar. Se nao recebeu o e-mail, enviaremos outro com prazer.
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 font-medium text-sm text-green-600 dark:text-green-400">
            Um novo link de verificacao foi enviado para o e-mail informado no cadastro.
        </div>
    @endif

    <div class="mt-4 flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <div>
                <x-primary-button>
                    Reenviar E-mail de Verificacao
                </x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="text-sm text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors">
                Sair
            </button>
        </form>
    </div>
</x-guest-layout>
