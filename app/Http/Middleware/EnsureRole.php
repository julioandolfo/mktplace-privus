<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Verifica se o usuário autenticado possui um dos roles permitidos.
     *
     * Uso na rota: middleware('role:admin,designer')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! $request->user()) {
            abort(401);
        }

        $userRole = $request->user()->role ?? 'admin';

        // admin tem acesso a tudo
        if ($userRole === 'admin') {
            return $next($request);
        }

        if (! in_array($userRole, $roles)) {
            abort(403, 'Acesso negado para este perfil de usuário.');
        }

        return $next($request);
    }
}
