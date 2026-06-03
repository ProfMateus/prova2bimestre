<?php
session_start();
require 'db.php';

$erro = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ra = trim($_POST['ra']);
    
    if (!empty($ra)) {
        $stmt = $pdo->prepare("SELECT * FROM alunos WHERE ra = ?");
        $stmt->execute([$ra]);
        $aluno = $stmt->fetch();
        
        if ($aluno) {
            if ($aluno['status'] === 'Concluído' || $aluno['status'] === 'Bloqueado') {
                $erro = "Acesso negado. Você já realizou ou teve sua avaliação bloqueada.";
            } else {
                // Atualiza status para Em Andamento para bloquear novos logins paralelos
                $stmtUpdate = $pdo->prepare("UPDATE alunos SET status = 'Em Andamento' WHERE ra = ?");
                $stmtUpdate->execute([$ra]);

                $_SESSION['aluno_ra'] = $aluno['ra'];
                $_SESSION['aluno_nome'] = $aluno['nome'];
                $_SESSION['tipo_prova'] = $aluno['tipo_prova'];
                
                // Inicializa as respostas na sessão para persistência (1 a 7)
                if (!isset($_SESSION['respostas'])) {
                    $_SESSION['respostas'] = array_fill(1, 7, "");
                }
                
                // Define o tempo de início (40 minutos)
                if (!isset($_SESSION['tempo_limite'])) {
                    $_SESSION['tempo_limite'] = time() + (40 * 60);
                }
                
                header("Location: prova.php");
                exit;
            }
        } else {
            $erro = "RA não encontrado no sistema.";
        }
    } else {
        $erro = "Por favor, digite o seu RA.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Login - Avaliação</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f9; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-container { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); width: 300px; text-align: center; }
        input[type="text"] { width: 100%; padding: 10px; margin: 15px 0; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { background: #0056b3; color: white; border: none; padding: 10px; width: 100%; border-radius: 4px; cursor: pointer; font-size: 16px; }
        button:hover { background: #004085; }
        .erro { color: red; margin-bottom: 15px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Acesso à Avaliação</h2>
        <?php if (!empty($erro)): ?>
            <div class="erro"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>
        <form action="" method="POST">
            <label for="ra">Digite seu RA:</label>
            <input type="text" id="ra" name="ra" required autocomplete="off">
            <button type="submit">Iniciar Prova</button>
        </form>
    </div>
</body>
</html>