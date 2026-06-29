<?php

namespace App\Services;

use App\Services\LockSessionService;
use \App\Models\Log;
use App\Models\Fechadura;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ApiControlIdService
{
    /**
     * Create a new class instance.
     */

    protected $fechadura;
    protected $sessao;

    public function __construct(Fechadura $fechadura)
    {
        $this->fechadura = $fechadura;
        $this->sessao = LockSessionService::conexao(
            $fechadura->ip, $fechadura->porta, $fechadura->usuario, $fechadura->senha
        );
    }

    public function loadUsers(){
        $route = 'http://' . $this->fechadura->ip . ':' . $this->fechadura->porta . '/load_objects.fcgi?session=' . $this->sessao;
        $response = Http::post($route, [
            "object" => "users"
        ]);

        $usuarios = $response->json()['users'] ?? [];

        // lista usuários em ordem alfabetica
        usort($usuarios, function($a, $b) {
            return strcmp($a['name'] ?? '', $b['name'] ?? '');
        });

        return $usuarios;
    }

    public function loadUser(int $userId){
        $route = 'http://' . $this->fechadura->ip . ':' . $this->fechadura->porta . '/load_objects.fcgi?session=' . $this->sessao;
        
        $response = Http::post($route, [
            'object' => 'users',
            'where' => [
                'users' => [
                    'id' => $userId
                ]
            ]
        ]);

        return $response->json()['users'][0] ?? null;
    }

    public function createUsers($faltantes){
        $url = 'http://' . $this->fechadura->ip . ':' . $this->fechadura->porta . '/create_objects.fcgi?session=' . $this->sessao;

        foreach ($faltantes as $codpes => $usuario) {

            $response = Http::asJson()->post($url, [
                'object' => 'users',
                'values' => [[
                    'id' => (int)$codpes,
                    'name' => $usuario['nompes'] ?? $usuario['name'],
                    'registration' => (string)$codpes,
                ]]
            ]);

            if($response->successful()){
                $foto = $usuario['foto'] ?? null;
                FotoUpdateService::updateFoto($this->fechadura, $codpes, $foto);
                $this->createUserGroups($codpes);
            }
        }
    }

    public function updateUsers($usuarios, $usersWithoutPhotos){
        $url = 'http://' . $this->fechadura->ip . ':' . $this->fechadura->porta . '/modify_objects.fcgi?session=' . $this->sessao;

        foreach($usuarios as $codpes => $usuario){
            // Atualiza informações básicas do usuário
            $response = Http::asJson()->post($url, [
                'object' => 'users',
                'values' => [
                    'id' => (int)$codpes,
                    'name' => $usuario['nompes'] ?? $usuario['name'],
                    'registration' => (string)$codpes,
                ],
                'where' => [
                    'users' => [
                        'id' => (int)$codpes
                    ]
                ]
            ]);

            if($response->successful()){
                // Atualizar foto apenas se o usuário não tiver foto
                if (in_array($codpes, $usersWithoutPhotos)) {
                    $foto = $usuario['foto'] ?? null;
                    FotoUpdateService::updateFoto($this->fechadura, $codpes, $foto);
                }
                $this->createUserGroups($codpes);
            }
        }
    }

    public function loadUserGroups(){
        $route = 'http://' . $this->fechadura->ip . ':' . $this->fechadura->porta . '/load_objects.fcgi?session=' . $this->sessao;
        $response = Http::post($route, [
            "object" => "user_groups"
        ]);

        return $response->json()['user_groups'] ?? [];
    }

    public function createUserGroups($codpes, $group = 1){
        $urlCreate = "http://" . $this->fechadura->ip . ':' . $this->fechadura->porta . "/create_objects.fcgi?session=" . $this->sessao;
        Http::post($urlCreate, [
            'object' => 'user_groups',
            'fields' => ['user_id','group_id'],
            'values' => [
                [
                    'user_id' => (int)$codpes,
                    'group_id' => $group
                ]
            ]
        ]);
    }

    public function loadLogs(){
        // 2 - Carregamento dos usuários cadastrados na fechadura
        $route = 'http://' . $this->fechadura->ip . ':' . $this->fechadura->porta . '/load_objects.fcgi?session=' . $this->sessao;
        $response = Http::post($route, [
            "object" => "users"
        ]);
        return $response;
    }

    // Atualiza os logs de acesso da fechadura no banco de dados local
    public function updateLogs()
    {
        $route = 'http://' . $this->fechadura->ip . ':' . $this->fechadura->porta . '/load_objects.fcgi?session=' . $this->sessao;
        $response = Http::post($route, [
            "object" => "access_logs",
            "limit" => 300,
            "order" => ["descending", "time"]
        ]);

        $logs = $response->json()['access_logs'] ?? [];

        $count = 0;
        foreach ($logs as $log) {

            Log::updateOrCreate(
                ['log_id_externo' => $log['id']],
                [
                    'event' => $log['event'],
                    'fechadura_id' => $this->fechadura->id,
                    'codpes' => $log['user_id'] ?? 0,
                    'datahora' => date('Y-m-d H:i:s', $log['time'])
                ]
            );
            $count++;
        }

        return $count;
    }

    public function uploadFoto($userId, $fotoName)
    {
        $url = 'http://' . $this->fechadura->ip . ':' . $this->fechadura->porta . '/user_set_image.fcgi?user_id='. $userId .'&timestamp='.time().'&match=0&session=' . $this->sessao;

        $response = Http::timeout(30)->withHeaders([
            'Content-Type' => 'application/octet-stream'
        ])->withBody(
            Storage::disk('fotos')->get($fotoName),
            'application/octet-stream'
        )->post($url);

        if ( $response->successful() ) {
            return [
                'success' => $response->json('success'),
                'message' => $response->json('success') ?
                    'Foto cadastrada com sucesso.' :
                    $this->getErrorMessage($response->json('errors'))
            ];
        }

        return [
            'success' => false,
            'message' => $this->getErrorMessage([['code' => 1]])
        ];
    }

    public function cadastrarSenha($userId, $senha)
    {
        // 1. Gerar o hash da senha
        $hashUrl = 'http://' . $this->fechadura->ip . ':' . $this->fechadura->porta . '/user_hash_password.fcgi?session=' . $this->sessao;
        $hashResponse = Http::asJson()->post($hashUrl, [
            'password' => (string)$senha
        ]);

        $hashedData = $hashResponse->json();

        // 2. Atualizar o usuário com o hash
        $updateUrl = 'http://' . $this->fechadura->ip . ':' . $this->fechadura->porta . '/modify_objects.fcgi?session=' . $this->sessao;

        $response = Http::asJson()->post($updateUrl, [
            'object' => 'users',
            'values' => [
                'password' => $hashedData['password'],
                'salt' => $hashedData['salt']
            ],
            'where' => [
                'users' => [
                    'id' => (int)$userId
                ]
            ]
        ]);

        return $response->successful();
    }

    public function testarFoto($fechadura, $foto)
    {
        $url = 'http://' . $fechadura->ip . ':' . $fechadura->porta . '/user_test_image.fcgi?session=' . $this->sessao;

        $fileContents = file_get_contents($foto);

        $response = Http::timeout(30)->withHeaders([
            'Content-Type' => 'application/octet-stream'
        ])->withBody(
            $fileContents,
            'application/octet-stream'
        )->post($url);

        $data = $response->json();

        return [
            'success' => $data['success'] ?? false,
            'message' => $data['success'] ?
                'Foto aprovada no teste de qualidade.' :
                $this->getErrorMessage($data['errors'] ?? []),
            'scores' => $data['scores'] ?? null,
            'errors' => $data['errors'] ?? []
        ];
    }

    // Método para traduzir códigos de erro
    private function getErrorMessage(array $errors)
    {
        $errorMessages = [
            1 => 'Erro nos parâmetros da requisição ou formato de imagem inválido.',
            2 => 'Rosto não detectado na imagem.',
            3 => 'Esta face já está cadastrada para outro usuário.',
            4 => 'Rosto não está centralizado na imagem.',
            5 => 'Rosto muito distante da câmera.',
            6 => 'Rosto muito próximo da câmera.',
            7 => 'Rosto não está posicionado corretamente (está torto).',
            8 => 'Imagem com baixa nitidez.',
            9 => 'Rosto muito próximo das bordas da imagem.'
        ];

        $error = head($errors);

        return $errorMessages[$error['code']] ?? 'Erro ao cadastrar a foto, erro não definido.';
    }

    //Exclui usuários da fechadura
    public function deleteUser($userId)
    {
        $url = 'http://' . $this->fechadura->ip . ':' . $this->fechadura->porta . '/destroy_objects.fcgi?session=' . $this->sessao;

        $data = [
            'object' => 'users',
            'where' => [
                'users' => [
                    'id' => (int)$userId
                ]
            ]
        ];

        $response = Http::asJson()->post($url, $data);

        if ($response->successful()) {
            $result = $response->json();
            return [
                'success' => true,
                'deleted' => $result['changes'] ?? 0
            ];
        }

        return [
            'success' => false,
            'error' => 'Falha ao excluir usuário',
            'status' => $response->status()
        ];
    }

    // Excluir usuários em lote (ao sincronizar)
    public function deleteUsersBatch(array $userIds)
    {
        if (empty($userIds)) {
            return ['success' => true, 'deleted' => 0];
        }

        $url = 'http://' . $this->fechadura->ip . ':' . $this->fechadura->porta . '/destroy_objects.fcgi?session=' . $this->sessao;

        $userIds = array_map('intval', $userIds);

        $data = [
            'object' => 'users',
            'where' => [
                'users' => [
                    'id' => $userIds
                ]
            ]
        ];

        $response = Http::asJson()->post($url, $data);

        if ($response->successful()) {
            $result = $response->json();
            return [
                'success' => true,
                'deleted' => $result['changes'] ?? 0
            ];
        }

        return [
            'success' => false,
            'error' => 'Falha ao excluir usuários da fechadura',
            'status' => $response->status()
        ];

    }

    // Busca foto do usuário na fechadura
    public function getFoto($userId)
    {
        $url = 'http://' . $this->fechadura->ip . ':' . $this->fechadura->porta .
            '/user_get_image.fcgi?user_id=' . $userId . '&session=' . $this->sessao;

        $response = Http::timeout(10)->get($url);

        if ($response->successful()) {
            return [
                'success' => true,
                'content' => $response->body(),
                'content_type' => $response->header('Content-Type', 'image/jpeg')
            ];
        }

        return [
            'success' => false,
            'content' => null
        ];
    }

    // Carrega todos os registros de user_roles da fechadura que sejam admins.
    public function loadUsersRoleAdmin()
    {
        $url = 'http://' . $this->fechadura->ip . ':' . $this->fechadura->porta . '/load_objects.fcgi?session=' . $this->sessao;
        $response = Http::post($url, [
            'object' => 'user_roles',
            'fields' => [
                'user_id'
            ],
            'where' => [
                'user_roles' => [
                    'role' => 1
                ]
            ]
        ]);
        return collect($response->json()['user_roles'])->pluck('user_id') ?? collect([]);
    }

    // Cria um registro de user_role para um usuário (torna-o administrador).
    public function createUserRoleAdmin($user)
    {
        $url = 'http://' . $this->fechadura->ip . ':' . $this->fechadura->porta . '/create_objects.fcgi?session=' . $this->sessao;
        $response = Http::asJson()->post($url, [
            'object' => 'user_roles',
            'values' => [
                [
                    'user_id' => (int)$user,
                    'role' => 1
                ]
            ]
        ]);
        return $response->successful();
    }

    // Remove o registro de user_role de um usuário (revoga privilégios de admin).
    public function deleteUserRoleAdmin($user)
    {
        $url = 'http://' . $this->fechadura->ip . ':' . $this->fechadura->porta . '/destroy_objects.fcgi?session=' . $this->sessao;
        $response = Http::asJson()->post($url, [
            'object' => 'user_roles',
            'where' => [
                'user_roles' => [
                    'user_id' => (int)$user,
                    'role' => 1
                ]
            ]
        ]);
        return $response->successful();
    }
}
