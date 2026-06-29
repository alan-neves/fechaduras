@extends('main')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5>Meus Dados</h5>
                </div>
                <div class="card-body text-center">
                  @if($foto)
                    <img src="data:image/jpeg;base64,{{ $foto }}"
                          class="img-thumbnail mb-3"
                          style="max-width: 200px;">
                  @else
                    <div class="alert alert-warning">
                      <i class="fas fa-user-slash"></i> Sem foto cadastrada
                    </div>
                  @endif

                    <h5>{{ $user->name }}</h5>
                    <p>Nº USP: {{ $user->codpes }}</p>
                    <a href="/meu-perfil/foto-geral" class="btn btn-primary">Atualizar minha foto</a>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5>Minhas Fechaduras</h5>
                </div>
                <div class="card-body">
                    @if(count($fechadurasStatus) === 0)
                        <p>Você não está cadastrado em nenhuma fechadura.</p>
                    @else
                        <table class="table table-hover">
                            <thead>
                                <tr><th>Local</th><th>Foto</th><th>Senha</th><th>Ação</th></tr>
                            </thead>
                            <tbody>
                                @foreach($fechadurasStatus as $item)
                                <tr>
                                    <td>{{ $item->fechadura->local }}</td>
                                    <td><span class="badge bg-{{ $item->tem_foto ? 'success' : 'danger' }}">{{ $item->tem_foto ? 'Sim' : 'Não' }}</span></td>
                                    <td><span class="badge bg-{{ $item->tem_senha ? 'success' : 'danger' }}">{{ $item->tem_senha ? 'Sim' : 'Não' }}</span></td>
                                    <td><a href="/meu-perfil/senha/{{ $item->fechadura->id }}" class="btn btn-sm btn-warning">Alterar senha</a></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
