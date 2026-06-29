@extends('main')

@section('content')
<div class="container">
  <div class="row">
    <div class="col-md-6">
      <div class="card">
        <div class="card-header">
          <a href="/meu-perfil" class="btn btn-secondary">Voltar</a>
          <h4>Minha foto atual</h4>
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
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card">
        <div class="card-header">
          <h4>Nova foto geral</h4>
        </div>
        <div class="card-body">
          @if ($errors->any())
            <div class="alert alert-danger">
              <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          @endif

          <!-- Webcam -->
          <div class="mb-4">
            <h5><i class="fas fa-camera"></i> Webcam</h5>
            <div class="text-center">
              <video id="video" autoplay class="img-thumbnail w-100 mb-2" style="max-height: 200px;"></video>
              <div class="mb-2">
                <button type="button" class="btn btn-primary btn-sm" onclick="ligarWebcam()">Ligar Câmera</button>
                <button type="button" class="btn btn-warning btn-sm" onclick="pararWebcam()">Desligar</button>
                <button type="button" class="btn btn-success btn-sm" onclick="tirarFoto()" id="btnTirar" disabled>Tirar Foto</button>
              </div>
              <div id="previewFoto" style="display: none;">
                <p><strong>Preview:</strong></p>
                <canvas id="canvas" class="img-thumbnail mb-2" style="max-width: 200px;"></canvas>
              </div>
            </div>
          </div>

          <!-- Upload de arquivo -->
          <div class="mb-4">
            <h5><i class="fas fa-upload"></i> Upload de arquivo</h5>
            <input type="file" class="form-control" id="arquivo" accept="image/*" onchange="previewArquivo()">
            <div id="previewArquivo" class="mt-2" style="display: none;">
              <img id="imgArquivo" class="img-thumbnail" style="max-width: 200px;">
            </div>
          </div>

          <form method="POST" action="/meu-perfil/foto-geral" id="formFoto">
            @csrf
            <input type="hidden" id="foto" name="foto">
            <button type="submit" class="btn btn-primary w-100" id="btnEnviar" disabled>
                <i class="fas fa-upload"></i> Enviar foto
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@section('javascripts_bottom')
<script>
let stream = null;
let fotoAtual = null;

function ligarWebcam() {
    navigator.mediaDevices.getUserMedia({ video: true, audio: false })
        .then(function(s) {
            stream = s;
            document.getElementById('video').srcObject = s;
            document.getElementById('btnTirar').disabled = false;
            document.getElementById('previewFoto').style.display = 'none';
        })
        .catch(function() {
            alert('Não foi possível acessar a webcam');
        });
}

function pararWebcam() {
    if (stream) {
        stream.getTracks().forEach(track => track.stop());
        document.getElementById('video').srcObject = null;
        document.getElementById('btnTirar').disabled = true;
    }
}

function tirarFoto() {
    const video = document.getElementById('video');
    const canvas = document.getElementById('canvas');
    const ctx = canvas.getContext('2d');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    ctx.drawImage(video, 0, 0);
    fotoAtual = canvas.toDataURL('image/jpeg');
    document.getElementById('previewFoto').style.display = 'block';
    document.getElementById('foto').value = fotoAtual;
    document.getElementById('btnEnviar').disabled = false;
    pararWebcam();
}

function previewArquivo() {
    const input = document.getElementById('arquivo');
    const preview = document.getElementById('previewArquivo');
    const img = document.getElementById('imgArquivo');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            img.src = e.target.result;
            preview.style.display = 'block';
            fotoAtual = e.target.result;
            document.getElementById('foto').value = fotoAtual;
            document.getElementById('btnEnviar').disabled = false;
            pararWebcam();
        };
        reader.readAsDataURL(input.files[0]);
    }
}

window.addEventListener('beforeunload', pararWebcam);
</script>
@endsection
