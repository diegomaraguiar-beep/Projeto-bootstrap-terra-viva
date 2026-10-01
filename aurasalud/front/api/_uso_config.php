<?php
/*
 * Configuração compartilhada do registro de uso (uso.php e uso_reporte.php).
 * Acessar este arquivo pelo navegador não mostra nada.
 */

// Pasta onde os registros são gravados. O ideal é ficar FORA da pasta pública
// do site (ex.: /home/usuario/dados/uso). Se ficar dentro, o .htaccess criado
// automaticamente bloqueia o acesso direto (Apache).
const USO_DIR = __DIR__ . '/../../_privado/uso';

// Senha do relatório (uso_reporte.php). TROQUE antes de publicar.
// Enquanto for o valor padrão, o relatório fica desativado.
const USO_CLAVE_REPORTE = 'TROQUE-ESTA-SENHA';

// Texto exato que o cliente aceita no onboarding, por versão (CONFIG.VERSION_TERMINOS no app).
const USO_TERMINOS = [
  '2026-10' => 'Entiendo que este es un programa informativo y de hábitos, que no sustituye la consulta médica, y que debo consultar a mi médico si tomo medicamentos o tengo una condición de salud.',
];

function uso_dir_listo(): bool {
  if (!is_dir(USO_DIR) && !@mkdir(USO_DIR, 0750, true)) return false;
  $ht = USO_DIR . '/.htaccess';
  if (!file_exists($ht)) @file_put_contents($ht, "Require all denied\nDeny from all\n");
  $ix = USO_DIR . '/index.html';
  if (!file_exists($ix)) @file_put_contents($ix, '');
  return is_writable(USO_DIR);
}

function uso_archivo_correo(string $email): string {
  return USO_DIR . '/c_' . hash('sha256', strtolower(trim($email))) . '.jsonl';
}

function uso_archivo_disp(string $disp): string {
  return USO_DIR . '/d_' . preg_replace('/[^a-z0-9]/i', '', $disp) . '.jsonl';
}

function uso_ip(): string {
  // Cloudflare / proxy reverso, se houver; senão o IP direto.
  foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP'] as $h) {
    if (!empty($_SERVER[$h]) && filter_var($_SERVER[$h], FILTER_VALIDATE_IP)) return $_SERVER[$h];
  }
  return $_SERVER['REMOTE_ADDR'] ?? '';
}
