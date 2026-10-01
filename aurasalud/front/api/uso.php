<?php
/*
 * Registro de uso do app (prova de entrega e de consumo do produto digital).
 *
 * O app envia só EVENTOS (o que e quando): abriu, aceitou os termos, marcou
 * tarefa, leu lição, fez teste... Nunca envia respostas, notas ou pontuações
 * de saúde. O servidor acrescenta IP, user-agent e hora do servidor.
 *
 * Cada cliente tem um arquivo próprio (um evento por linha), o que facilita
 * gerar o relatório para uma disputa em uso_reporte.php.
 */
declare(strict_types=1);
require __DIR__ . '/_uso_config.php';

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit; }

$crudo = file_get_contents('php://input', false, null, 0, 4096);
$d = json_decode($crudo ?: '', true);
if (!is_array($d)) { http_response_code(400); exit; }

const EVENTOS = ['abrir','consentimiento','inicio','tarea','contenido','test','hito','desbloqueo',
                 'recordatorio','respaldo','restaurar','reinicio','correo'];
const EXTRAS  = ['tarea','id','test','hito','version','producto','inicio','hora','dia_test'];

$ev = (string)($d['ev'] ?? '');
if (!in_array($ev, EVENTOS, true)) { http_response_code(400); exit; }

$email = strtolower(trim((string)($d['email'] ?? '')));
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $email = '';
$disp = substr(preg_replace('/[^a-z0-9]/i', '', (string)($d['disp'] ?? '')), 0, 40);
if ($email === '' && $disp === '') { http_response_code(204); exit; }

$corta = fn($v, $n) => mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', '', (string)$v), 0, $n);

$reg = [
  'srv'   => gmdate('c'),
  'ev'    => $ev,
  'email' => $email,
  'disp'  => $disp,
  'dia'   => max(0, min(9999, (int)($d['dia'] ?? 0))),
  'ts'    => $corta($d['ts'] ?? '', 40),
  'tz'    => $corta($d['tz'] ?? '', 64),
  'url'   => $corta($d['url'] ?? '', 200),
  'ip'    => uso_ip(),
  'ua'    => $corta($_SERVER['HTTP_USER_AGENT'] ?? '', 300),
];
foreach (EXTRAS as $k) if (isset($d[$k])) $reg[$k] = $corta($d[$k], 80);

if (!uso_dir_listo()) { http_response_code(204); exit; }

// limite simples por IP: 200 eventos a cada 10 minutos
$lim = USO_DIR . '/_lim_' . hash('crc32b', $reg['ip']) . '_' . intdiv(time(), 600);
$n = (int)@file_get_contents($lim);
if ($n >= 200) { http_response_code(429); exit; }
@file_put_contents($lim, (string)($n + 1), LOCK_EX);
if (mt_rand(1, 200) === 1) {  // limpeza ocasional dos contadores antigos
  foreach (glob(USO_DIR . '/_lim_*') ?: [] as $f) if (filemtime($f) < time() - 1800) @unlink($f);
}

$linea = json_encode($reg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
$dest = $email !== '' ? uso_archivo_correo($email) : uso_archivo_disp($disp);
@file_put_contents($dest, $linea, FILE_APPEND | LOCK_EX);

// mapa dispositivo -> e-mail: liga eventos anteriores ao cadastro do e-mail
if ($email !== '' && $disp !== '') {
  $mapa = USO_DIR . '/m_' . $disp . '.txt';
  if (!file_exists($mapa)) @file_put_contents($mapa, $email, LOCK_EX);
}

http_response_code(204);
