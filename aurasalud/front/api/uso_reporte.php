<?php
/*
 * Relatório de entrega e uso para responder a chargebacks / disputas.
 *
 * Uso:  /front/api/uso_reporte.php?email=cliente@exemplo.com&clave=SUA-SENHA
 * Depois: Ctrl+P → "Salvar como PDF" e anexe na resposta da disputa.
 *
 * Troque USO_CLAVE_REPORTE em _uso_config.php antes de publicar.
 */
declare(strict_types=1);
require __DIR__ . '/_uso_config.php';

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');

$clave = (string)($_GET['clave'] ?? '');
if (USO_CLAVE_REPORTE === 'TROQUE-ESTA-SENHA' || !hash_equals(USO_CLAVE_REPORTE, $clave)) {
  http_response_code(403);
  exit('Acesso negado. Defina USO_CLAVE_REPORTE em _uso_config.php e informe ?clave=.');
}

$email = strtolower(trim((string)($_GET['email'] ?? '')));
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

function leer_jsonl(string $f): array {
  if (!is_file($f)) return [];
  $out = [];
  foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $l) {
    $r = json_decode($l, true);
    if (is_array($r)) $out[] = $r;
  }
  return $out;
}

$ev = [];
if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
  $ev = leer_jsonl(uso_archivo_correo($email));
  // eventos do mesmo aparelho antes de o e-mail ser informado
  $disps = array_unique(array_filter(array_column($ev, 'disp')));
  foreach ($disps as $d) foreach (leer_jsonl(uso_archivo_disp($d)) as $r) $ev[] = $r;
  usort($ev, fn($a, $b) => strcmp($a['srv'] ?? '', $b['srv'] ?? ''));
}

$NOMBRES = [
  'abrir' => 'Abriu o app', 'consentimiento' => 'Aceitou os termos', 'inicio' => 'Iniciou o protocolo',
  'tarea' => 'Concluiu tarefa', 'contenido' => 'Abriu conteúdo', 'test' => 'Fez teste', 'hito' => 'Marco atingido',
  'desbloqueo' => 'Desbloqueou módulo pago', 'recordatorio' => 'Criou lembrete diário', 'respaldo' => 'Salvou backup',
  'restaurar' => 'Restaurou backup', 'reinicio' => 'Reiniciou o protocolo', 'correo' => 'Cadastrou e-mail',
  'juego' => 'Jogou jogo de memória',
];

$dias = array_unique(array_map(fn($r) => substr($r['srv'] ?? '', 0, 10), $ev));
$ips  = array_unique(array_filter(array_column($ev, 'ip')));
$conts = array_count_values(array_column($ev, 'ev'));
$consent = array_values(array_filter($ev, fn($r) => ($r['ev'] ?? '') === 'consentimiento'));
?><!doctype html>
<html lang="pt-br"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Comprovante de entrega e uso</title>
<style>
body{font:14px/1.5 -apple-system,Segoe UI,Roboto,sans-serif;color:#1a3a4a;max-width:960px;margin:24px auto;padding:0 16px;background:#fff}
h1{font-size:22px;margin:0 0 4px}h2{font-size:16px;margin:26px 0 8px}
.sub{color:#6b8a9a;margin:0 0 18px}
table{width:100%;border-collapse:collapse;font-size:12.5px}
th,td{border:1px solid #dde7ec;padding:6px 8px;text-align:left;vertical-align:top}
th{background:#f1f5f8}
.res td:first-child{width:260px;color:#6b8a9a}
.ua{color:#6b8a9a;font-size:11px;word-break:break-all}
form{display:flex;gap:8px;margin-bottom:18px}input{padding:8px;font:inherit;flex:1}button{padding:8px 14px;font:inherit}
@media print{form{display:none}body{margin:0}}
</style></head><body>
<form method="get"><input type="email" name="email" placeholder="e-mail do cliente" value="<?= $h($email) ?>">
<input type="hidden" name="clave" value="<?= $h($clave) ?>"><button>Gerar</button></form>

<h1>Comprovante de entrega e uso do produto digital</h1>
<p class="sub">Proof of delivery and usage · Gerado em <?= $h(gmdate('Y-m-d H:i')) ?> UTC</p>

<?php if ($email === ''): ?>
  <p>Informe o e-mail do cliente.</p>
<?php elseif (!$ev): ?>
  <p>Nenhum registro encontrado para <b><?= $h($email) ?></b>.</p>
<?php else: ?>
<table class="res">
  <tr><td>Cliente (e-mail)</td><td><b><?= $h($email) ?></b></td></tr>
  <tr><td>Primeiro acesso</td><td><?= $h($ev[0]['srv'] ?? '') ?> UTC</td></tr>
  <tr><td>Último acesso</td><td><?= $h(end($ev)['srv'] ?? '') ?> UTC</td></tr>
  <tr><td>Dias distintos com uso</td><td><?= count($dias) ?></td></tr>
  <tr><td>Total de eventos registrados</td><td><?= count($ev) ?></td></tr>
  <tr><td>Endereços IP utilizados</td><td><?= $h(implode(', ', $ips)) ?></td></tr>
  <tr><td>Resumo por tipo</td><td><?php foreach ($conts as $k => $n) echo $h(($NOMBRES[$k] ?? $k) . ': ' . $n) . '<br>'; ?></td></tr>
</table>

<?php if ($consent): $c = $consent[0]; ?>
<h2>Aceite dos termos (click-wrap)</h2>
<table class="res">
  <tr><td>Data/hora (servidor)</td><td><?= $h($c['srv'] ?? '') ?> UTC</td></tr>
  <tr><td>IP</td><td><?= $h($c['ip'] ?? '') ?></td></tr>
  <tr><td>Versão</td><td><?= $h($c['version'] ?? '') ?></td></tr>
  <tr><td>Texto aceito</td><td><?= $h(USO_TERMINOS[$c['version'] ?? ''] ?? '—') ?></td></tr>
  <tr><td>Navegador</td><td class="ua"><?= $h($c['ua'] ?? '') ?></td></tr>
</table>
<?php endif; ?>

<h2>Registro detalhado</h2>
<table>
  <tr><th>Data/hora (UTC)</th><th>Evento</th><th>Detalhe</th><th>Dia do protocolo</th><th>IP</th><th>Navegador</th></tr>
  <?php foreach ($ev as $r):
    $det = [];
    foreach (['tarea','id','test','hito','producto','version','inicio','hora','dia_test'] as $k) if (!empty($r[$k])) $det[] = $k . ': ' . $r[$k];
  ?>
  <tr>
    <td><?= $h(str_replace('T', ' ', substr($r['srv'] ?? '', 0, 19))) ?></td>
    <td><?= $h($NOMBRES[$r['ev'] ?? ''] ?? ($r['ev'] ?? '')) ?></td>
    <td><?= $h(implode(' · ', $det)) ?></td>
    <td><?= $h($r['dia'] ?? '') ?></td>
    <td><?= $h($r['ip'] ?? '') ?></td>
    <td class="ua"><?= $h($r['ua'] ?? '') ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>
</body></html>
