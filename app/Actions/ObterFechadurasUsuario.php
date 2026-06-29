<?php

namespace App\Actions;

use App\Services\ApiControlIdService;
use App\Models\User;
use App\Models\Fechadura;
use Illuminate\Database\Eloquent\Collection;

class ObterFechadurasUsuario
{

    // Retorna todas as fechaduras às quais o usuário tem acesso
    public static function handle(User $user): Collection
    {
        return Fechadura::all()->filter(function ($fechadura) use ($user) {
            try {
                return (new ApiControlIdService($fechadura))->loadUser($user->codpes) !== null;
            } catch (\Exception) {
                return false;
            }
        })->values();
    }

}
