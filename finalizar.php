<?php
session_start();
require 'db.php';

if (!isset($_SESSION['aluno_ra'])) {
    header("Location: index.php");
    exit;
}

$ra = $_SESSION['aluno_ra'];
$tipo_prova = $_SESSION['tipo_prova'];
$motivo = isset($_GET['motivo']) ? $_GET['motivo'] : 'regular';

$nota = 0;
$status = 'Concluído';

if ($motivo === 'fraude') {
    $nota = 0;
    $status = 'Bloqueado';
} else {
    // Busca o gabarito oficial do tipo de prova do aluno
    $stmt = $pdo->prepare("SELECT numero_questao, correta FROM questoes WHERE tipo_prova = ?");
    $stmt->execute([$tipo_prova]);
    $gabarito = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // Retorna array ex: [1 => 'B', 2 => 'C'...]

    // Compara as respostas armazenadas na sessão com o gabarito oficial
    for ($i = 1; $i <= 7; $i++) {
        $resposta_aluno = isset($_SESSION['respostas'][$i]) ? $_SESSION['respostas'][$i] : "";
        
        if (!empty($resposta_aluno) && $resposta_aluno === $gabarito[$i]) {
            $nota += 1.0; // Correta soma 1 ponto
        }
        // Respostas incorretas ou em branco continuam valendo zero.
    }
}

// Atualiza o banco de dados de forma definitiva
$stmtUpdate = $pdo->prepare("UPDATE alunos SET nota = ?, status = ? WHERE ra = ?");
$stmtUpdate->execute([$nota, $status, $ra]);

// Destrói a sessão para evitar reentradas
session_unset();
session_destroy();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Avaliação Finalizada</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f9; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .resultado-container { background: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); text-align: center; max-width: 400px; }
        h2 { color: #333; }
        .nota { font-size: 48px; font-weight: bold; color: #0056b3; margin: 20px 0; }
        .alerta { color: #d9534f; font-weight: bold; }
        .sucesso { color: #28a745; font-weight: bold; }
    </style>
</head>
<body>
    <div class="resultado-container">
        <?php if ($status === 'Bloqueado'): ?>
            <h2 class="alerta">Avaliação Bloqueada</h2>
            <p>Você violou as regras de segurança mudando de aba ou tentando capturar a tela mais de uma vez.</p>
            <div class="nota" style="color: #d9534f;">0.0</div>
        <?php else: ?>
            <h2 class="sucesso">Avaliação Concluída!</h2>
            <p>Suas respostas foram processadas e enviadas com sucesso.</p>
            <?php if ($motivo === 'tempo'): ?>
                <p class="alerta">(O tempo limite de 40 minutos expirou)</p>
            <?php endif; ?>
            <div class="nota"><?= number_format($nota, 1, ',', '.') ?> / 7,0</div>
        <?php endif; ?>
        <p>Sua sessão foi encerrada de maneira segura.</p>
    </div>
</body>
</html>