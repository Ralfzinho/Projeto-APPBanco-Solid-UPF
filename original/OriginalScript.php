<?php
declare(strict_types=1);
require_once __DIR__ . '/class.php';

/** Helpers de apresentação (só aqui!) */
function money(float $v): string { return 'R$ ' . number_format($v, 2, ',', '.'); }

/** Dados de teste */
$dados = criar_dados_mock();
$banco = $dados['banco'];
$op    = $dados['operacao'];
[$cc1, $cp1, $cc2] = $dados['contas'];

/** Operações de exemplo */
$op->depositar($cc1, 300.00);
$op->sacar($cc1, 150.00);
$op->transferir($cp1, $cc2, 200.00);
$cp1->aplicarRendimento(2);

/** Saída HTML simples */
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>Banco - Testes</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{font-family:system-ui,Segoe UI,Arial,sans-serif;background:#0f172a;color:#e2e8f0;margin:0;padding:24px}
    h1,h2{margin:0 0 12px}
    .grid{display:grid;gap:16px}
    @media(min-width:800px){.grid{grid-template-columns:1fr 1fr}}
    .card{background:#111827;border-radius:14px;padding:16px;box-shadow:0 8px 24px rgba(0,0,0,.25)}
    table{width:100%;border-collapse:collapse}
    th,td{padding:10px;border-bottom:1px solid #1f2937;text-align:left}
    .right{text-align:right}
    .badge{display:inline-block;background:#2563eb;padding:4px 10px;border-radius:999px}
    small{opacity:.8}
  </style>
</head>
<body>
  <h1>Simulação Bancária (PHP OO)</h1>

  <div class="grid">
    <div class="card">
      <h2>Clientes</h2>
      <table>
        <thead><tr><th>Nome</th><th>CPF</th><th>Telefone</th></tr></thead>
        <tbody>
        <?php foreach ($banco->listarClientes() as $c): ?>
          <tr>
            <td><?=htmlspecialchars($c->nome)?></td>
            <td><?=htmlspecialchars($c->cpf)?></td>
            <td><?=htmlspecialchars($c->telefone)?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="card">
      <h2>Contas</h2>
      <table>
        <thead><tr><th>Número</th><th>Tipo</th><th>Cliente</th><th class="right">Saldo</th></tr></thead>
        <tbody>
        <?php foreach ($banco->listarContas() as $conta): ?>
          <tr>
            <td><?=$conta->getNumero()?></td>
            <td><?=ucfirst($conta->getTipo())?></td>
            <td><?=htmlspecialchars($conta->getCliente()->nome)?></td>
            <td class="right"><?=money($conta->getSaldo())?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="card">
      <h2>Transações</h2>
      <table>
        <thead><tr><th>#</th><th>Tipo</th><th>Contas</th><th>Data</th><th class="right">Valor</th></tr></thead>
        <tbody>
        <?php foreach ($op->getLog() as $t): ?>
          <tr>
            <td><?=$t->id?></td>
            <td><span class="badge"><?=$t->tipo?></span></td>
            <td>
              <?=$t->contaNumero?>
              <?php if ($t->contaDestinoNumero): ?> → <?=$t->contaDestinoNumero?><?php endif; ?>
            </td>
            <td><small><?=$t->data->format('d/m/Y H:i:s')?></small></td>
            <td class="right"><?=money($t->valor)?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="card">
      <h2>Extrato rápido</h2>
      <p><strong>CC (<?=$cc1->getNumero()?>):</strong> <?=money($cc1->getSaldo())?></p>
      <p><strong>CP (<?=$cp1->getNumero()?>):</strong> <?=money($cp1->getSaldo())?> <small>(com rendimento)</small></p>
      <p><strong>CC Cliente 2 (<?=$cc2->getNumero()?>):</strong> <?=money($cc2->getSaldo())?></p>
    </div>
  </div>
</body>
</html>
