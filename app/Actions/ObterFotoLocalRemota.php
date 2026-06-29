<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Uspdev\Wsfoto;

class ObterFotoLocalRemota
{
    /**
     * Retorna a imagem codificada em Base64
     * @param User $user
     * @return string|null
     */
    public static function handle(User $user): ?string
    {
        if( $user->foto && Storage::disk('fotos')->exists($user->foto) ) {
            $foto = base64_encode(Storage::disk('fotos')->get($user->foto));
        }
        else {
            $foto = Wsfoto::obter($user->codpes);
        }

        return $foto ?? null;
    }
}
