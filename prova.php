<?php
session_start();
require 'db.php';

// Bloqueia acesso direto sem login
if (!isset($_SESSION['aluno_ra'])) {
    header("Location: index.php");
    exit;
}

$ra = $_SESSION['aluno_ra'];
$nome = $_SESSION['aluno_nome'];
$tipo_prova = $_SESSION['tipo_prova'];

// Verifica se o tempo estourou no servidor
if (time() > $_SESSION['tempo_limite']) {
    header("Location: finalizar.php?motivo=tempo");
    exit;
}

// Controla a questão atual (1 a 7)
$questao_atual = isset($_GET['q']) ? (int)$_GET['q'] : 1;
if ($questao_atual < 1) $questao_atual = 1;
if ($questao_atual > 7) $questao_atual = 7;

// Salva a resposta da questão anterior na sessão se enviada via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $q_respondida = (int)$_POST['questao_atual'];
    $_SESSION['respostas'][$q_respondida] = isset($_POST['alternativa']) ? $_POST['alternativa'] : "";
    
    // Direcionamento dos botões (Anterior, Próxima ou Finalizar)
    if (isset($_POST['acao'])) {
        if ($_POST['acao'] === 'anterior' && $questao_atual > 1) {
            header("Location: prova.php?q=" . ($questao_atual - 1));
            exit;
        } elseif ($_POST['acao'] === 'proxima' && $questao_atual < 7) {
            header("Location: prova.php?q=" . ($questao_atual + 1));
            exit;
        } elseif ($_POST['acao'] === 'finalizar') {
            header("Location: finalizar.php");
            exit;
        }
    }
}

// Busca a questão atual correspondente ao tipo de prova do aluno
$stmt = $pdo->prepare("SELECT * FROM questoes WHERE tipo_prova = ? AND numero_questao = ?");
$stmt->execute([$tipo_prova, $questao_atual]);
$questao = $stmt->fetch();

// Calcula o tempo restante para passar ao Javascript
$tempo_restante = $_SESSION['tempo_limite'] - time();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Avaliação em Andamento</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f9; padding: 20px; user-select: none; }
        .container { max-width: 700px; background: #fff; margin: 0 auto; padding: 30px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); position: relative; }
        .header-info { display: flex; justify-content: space-between; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 20px; font-weight: bold; }
        .timer { color: #d9534f; font-size: 18px; }
        .questao { margin-bottom: 20px; }
        .alternativa { display: block; margin: 10px 0; padding: 10px; border: 1px solid #ddd; border-radius: 4px; cursor: pointer; }
        .alternativa:hover { background: #f0f0f0; }
        .nav-buttons { display: flex; justify-content: space-between; margin-top: 30px; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        .btn-nav { background: #6c757d; color: white; }
        .btn-submit { background: #28a745; color: white; }
        .btn:hover { opacity: 0.9; }
    </style>
</head>
<body>

<div class="container">
    <div class="header-info">
        <div>Aluno: <?= htmlspecialchars($nome) ?> (RA: <?= htmlspecialchars($ra) ?>)</div>
        <div class="timer">Tempo restante: <span id="regressivo">--:--</span></div>
    </div>

    <h3>Questão <?= $questao_atual ?> de 7</h3>
    
    <form id="form-prova" action="" method="POST">
        <input type="hidden" name="questao_atual" value="<?= $questao_atual ?>">
        
        <div class="questao">
            <p><strong><?= htmlspecialchars($questao['enunciado']) ?></strong></p>
            
            <?php 
            $res_salva = $_SESSION['respostas'][$questao_atual]; 
            $alts = ['A' => $questao['alt_a'], 'B' => $questao['alt_b'], 'C' => $questao['alt_c'], 'D' => $questao['alt_d']];
            foreach ($alts as $letra => $texto): 
            ?>
                <label class="alternativa">
                    <input type="radio" name="alternativa" value="<?= $letra ?>" <?= $res_salva === $letra ? 'checked' : '' ?>>
                    <strong><?= $letra ?>)</strong> <?= htmlspecialchars($texto) ?>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="nav-buttons">
            <?php if ($questao_atual > 1): ?>
                <button type="submit" name="acao" value="anterior" class="btn btn-nav">← Anterior</button>
            <?php else: ?>
                <div></div>
            <?php endif; ?>

            <?php if ($questao_atual < 7): ?>
                <button type="submit" name="acao" value="proxima" class="btn btn-nav">Próxima →</button>
            <?php else: ?>
                <button type="submit" name="acao" value="finalizar" class="btn btn-submit" onclick="return confirm('Deseja realmente finalizar a prova?')">Finalizar Avaliação</button>
            <?php endif; ?>
        </div>
    </form>
</div>

<script>
    // 1. Controle do Timer Descrescente
    let tempoRestante = <?= $tempo_restante ?>;
    const display = document.getElementById('regressivo');

    const cronometro = setInterval(() => {
        let minutos = Math.floor(tempoRestante / 60);
        let segundos = tempoRestante % 60;

        minutos = minutos < 10 ? '0' + minutos : minutos;
        segundos = segundos < 10 ? '0' + segundos : segundos;

        display.textContent = minutos + ":" + segundos;

        if (--tempoRestante < 0) {
            clearInterval(cronometro);
            alert("O tempo acabou! Suas respostas serão salvas.");
            window.location.href = "finalizar.php?motivo=tempo";
        }
    }, 1000);

    // 2. Anti-fraude: Detecção de Mudança de Aba e Suposição de Print/Atalhos
    let avisos = 0;

    function aplicarPunicao() {
        alert("Infração das regras detectada novamente. Sua prova foi zerada e bloqueada.");
        window.location.href = "finalizar.php?motivo=fraude";
    }

    function dispararAviso() {
        avisos++;
        if (avisos === 1) {
            alert("AVISO: É proibido mudar de aba, tirar prints ou utilizar atalhos do teclado. Na próxima infração, sua prova será zerada!");
        } else if (avisos >= 2) {
            aplicarPunicao();
        }
    }

    // Monitora saída da aba/janela
    document.addEventListener("visibilitychange", () => {
        if (document.visibilityState === 'hidden') {
            dispararAviso();
        }
    });

    // Monitora se a janela perde o foco total
    window.addEventListener('blur', () => {
        dispararAviso();
    });

    // Bloqueia botão direito e intercepta teclas comuns de Print (PrintScreen/Atalhos)
    document.addEventListener('contextmenu', event => event.preventDefault());

    document.addEventListener('keyup', (e) => {
        if (e.key === 'PrintScreen') {
            navigator.clipboard.writeText(''); // Limpa a área de transferência
            dispararAviso();
        }
    });

    document.addEventListener('keydown', (e) => {
        // Intercepta Ctrl+P (Print), Ctrl+S, ou chaves de desenvolvedor
        if (e.ctrlKey && (e.key === 'p' || e.key === 'P' || e.key === 's' || e.key === 'S')) {
            e.preventDefault();
            dispararAviso();
        }
    });
</script>

</body>
</html>